<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\DrugStock;
use App\Models\Encounter;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\AuditLogger;
use App\Services\ClinicalDecisionSupport;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function __construct(private ClinicalDecisionSupport $cds) {}

    /**
     * GET /api/pharmacy/prescriptions?status=&encounter_id=
     */
    public function indexPrescriptions(Request $request)
    {
        $query = Prescription::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * POST /api/pharmacy/prescriptions
     * Roles: doctor, admin
     */
    public function storePrescription(Request $request)
    {
        if (! $request->filled('encounter_id') || ! $request->filled('drug_name')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id and drug_name are required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $patient = Patient::findOrFail($encounter->patient_id);

        $activeAllergies = Allergy::where('patient_id', $patient->id)->get();
        $currentRx = Prescription::where('encounter_id', $encounter->id)->get();
        $alerts = $this->cds->checkPrescriptionSafety($request->input('drug_name'), $patient, $activeAllergies, $currentRx);

        $prescription = Prescription::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'drug_name' => $request->input('drug_name'),
            'formulation' => $request->input('formulation'),
            'dose' => $request->input('dose'),
            'route' => $request->input('route'),
            'frequency' => $request->input('frequency'),
            'duration' => $request->input('duration'),
            'is_pediatric_dose' => $patient->isPediatric(),
            'cds_alerts' => json_encode($alerts),
            'prescribed_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log(
            $request->user(), 'prescribe', 'encounter', $encounter->id,
            "{$request->input('drug_name')} alerts=".count($alerts)
        );

        $result = $prescription->toArray();
        $result['cds_alerts_list'] = $alerts;

        return response()->json($result, 201);
    }

    /**
     * POST /api/pharmacy/prescriptions/{prescription}/dispense
     * Roles: pharmacist, admin
     * Deducts one unit from matching drug stock (if tracked) and warns —
     * rather than blocks — when stock is already at zero, since refusing
     * to record a real-world dispense that already happened would just
     * make the data wrong, not prevent the dispense.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        $stock = DrugStock::where('drug_name', $prescription->drug_name)->first();
        $stockWarning = null;

        if ($stock) {
            if ($stock->quantity_on_hand <= 0) {
                $stockWarning = 'Out of stock — dispensing recorded but stock is at zero. Reorder immediately.';
            } else {
                $stock->quantity_on_hand = max(0, $stock->quantity_on_hand - 1);
                $stock->save();
            }
        }

        $prescription->update([
            'status' => 'dispensed',
            'dispensed_by' => $request->user()->id,
            'dispensed_at' => now(),
        ]);

        AuditLogger::log($request->user(), 'dispense_medication', 'prescription', $prescription->id);

        $result = $prescription->toArray();
        $result['stock_warning'] = $stockWarning;

        return response()->json($result);
    }

    /**
     * POST /api/pharmacy/prescriptions/{prescription}/administer
     * Roles: nurse, admin
     */
    public function administer(Request $request, Prescription $prescription)
    {
        $record = MedicationAdministration::create([
            'prescription_id' => $prescription->id,
            'administered_by' => $request->user()->id,
            'administered_at' => now(),
            'dose_given' => $request->input('dose_given'),
            'notes' => $request->input('notes'),
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log($request->user(), 'administer_medication', 'prescription', $prescription->id);

        return response()->json($record, 201);
    }

    /**
     * GET /api/pharmacy/stock
     */
    public function stock()
    {
        return response()->json(DrugStock::orderBy('drug_name')->get());
    }

    /**
     * POST /api/pharmacy/stock
     * Roles: pharmacist, admin
     * Upsert by drug_name — creating a new line or topping up an existing one.
     */
    public function upsertStock(Request $request)
    {
        if (! $request->filled('drug_name')) {
            return response()->json(['error' => 'missing_fields'], 400);
        }

        $stock = DrugStock::firstOrNew(['drug_name' => $request->input('drug_name')]);
        $stock->quantity_on_hand = $request->input('quantity_on_hand', $stock->quantity_on_hand ?? 0);
        $stock->reorder_level = $request->input('reorder_level', $stock->reorder_level ?? 10);
        $stock->unit = $request->input('unit', $stock->unit ?? 'tablets');
        $stock->is_controlled = $request->input('is_controlled', $stock->is_controlled ?? false);
        $stock->save();

        AuditLogger::log($request->user(), 'update_stock', 'drug_stock', $stock->id, $request->input('drug_name'));

        return response()->json($stock);
    }
}
