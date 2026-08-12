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
        $query = Prescription::with(['patient.allergies', 'prescribedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }

        $prescriptions = $query->latest()->get()->map(function (Prescription $prescription) {
            $payload = $prescription->toArray();
            $payload['patient_name'] = $prescription->patient?->full_name;
            $payload['patient_uid'] = $prescription->patient?->patient_uid;
            $payload['prescribed_by_name'] = $prescription->prescribedBy?->full_name;
            $payload['patient_allergies'] = $prescription->patient?->allergies?->map(fn (Allergy $allergy) => [
                'substance' => $allergy->substance,
                'reaction' => $allergy->reaction,
                'severity' => $allergy->severity,
            ])?->toArray() ?? [];

            return $payload;
        });

        return response()->json($prescriptions);
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
        $result['patient_name'] = $patient->full_name;
        $result['patient_uid'] = $patient->patient_uid;
        $result['patient_allergies'] = $patient->allergies->map(fn (Allergy $allergy) => [
            'substance' => $allergy->substance,
            'reaction' => $allergy->reaction,
            'severity' => $allergy->severity,
        ])->toArray();
        $result['cds_alerts_list'] = $alerts;

        return response()->json($result, 201);
    }

    /**
     * POST /api/pharmacy/prescriptions/{prescription}/dispense
     * Roles: pharmacist, admin
     * Body (optional): { confirm_unlisted_drug: bool, confirm_allergy_override: bool }
     *
     * Allergy check comes first and is a hard block, not advisory. The
     * alert is already computed and stored on the prescription at
     * prescribing time (cds_alerts); we don't re-run CDS here, we just
     * refuse to proceed past it without an explicit, individually-recorded
     * override. Stock/catalog checks are a data-quality concern — this is
     * a patient-safety one, so it's checked before them.
     *
     * If the prescribed drug isn't in the DrugStock catalog at all, we
     * can't verify it's a real, tracked item — dispensing it silently
     * would mean the system has zero visibility into what just left the
     * pharmacy. Rather than blocking outright (the catalog may genuinely
     * be incomplete), this requires an explicit confirm_unlisted_drug
     * flag before proceeding, and always records a clear warning either
     * way — never a silent, unflagged dispense.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        $alerts = json_decode($prescription->cds_alerts ?? '[]', true) ?: [];
        $allergyAlerts = array_values(array_filter($alerts, fn ($a) => str_starts_with($a, 'ALLERGY ALERT')));

        $allergyOverride = null;

        if ($allergyAlerts) {
            if (! $request->boolean('confirm_allergy_override')) {
                return response()->json([
                    'error' => 'allergy_alert',
                    'message' => implode(' ', $allergyAlerts).' Confirm to dispense anyway.',
                    'requires_confirmation' => true,
                    'requires_allergy_override' => true,
                ], 422);
            }

            $allergyOverride = implode(' ', $allergyAlerts);
        }

        // Case/whitespace-insensitive match — a doctor typing "Amoxicillin "
        // or "AMOXICILLIN" for a stock item filed as "amoxicillin" should
        // still resolve to the same catalog entry, not fall through to the
        // unlisted-drug path.
        $stock = DrugStock::whereRaw('LOWER(TRIM(drug_name)) = ?', [strtolower(trim($prescription->drug_name))])->first();
        $stockWarning = null;

        if (! $stock) {
            if (! $request->boolean('confirm_unlisted_drug')) {
                return response()->json([
                    'error' => 'drug_not_in_catalog',
                    'message' => "\"{$prescription->drug_name}\" is not in the pharmacy stock catalog, so its availability can't be verified. Confirm to dispense anyway.",
                    'requires_confirmation' => true,
                ], 422);
            }

            $stockWarning = "\"{$prescription->drug_name}\" is not tracked in the pharmacy stock catalog — dispensed without stock verification. Consider adding it to the catalog.";
        } elseif ($stock->quantity_on_hand <= 0) {
            $stockWarning = 'Out of stock — dispensing recorded but stock is at zero. Reorder immediately.';
        } else {
            $stock->quantity_on_hand = max(0, $stock->quantity_on_hand - 1);
            $stock->save();
        }

        $prescription->update([
            'status' => 'dispensed',
            'dispensed_by' => $request->user()->id,
            'dispensed_at' => now(),
            'allergy_override_by' => $allergyOverride ? $request->user()->id : null,
            'allergy_override_at' => $allergyOverride ? now() : null,
        ]);

        if ($allergyOverride) {
            AuditLogger::log($request->user(), 'dispense_allergy_override', 'prescription', $prescription->id, $allergyOverride);
        }
        AuditLogger::log($request->user(), 'dispense_medication', 'prescription', $prescription->id, $stockWarning);

        $result = $prescription->toArray();
        $result['patient_name'] = $prescription->patient?->full_name;
        $result['patient_uid'] = $prescription->patient?->patient_uid;
        $result['patient_allergies'] = $prescription->patient?->allergies->map(fn (Allergy $allergy) => [
            'substance' => $allergy->substance,
            'reaction' => $allergy->reaction,
            'severity' => $allergy->severity,
        ])->toArray() ?? [];
        $result['stock_warning'] = $stockWarning;
        $result['allergy_override_warning'] = $allergyOverride;

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

        // Reuse an existing row that only differs by case/whitespace instead
        // of creating a duplicate catalog entry (see the same normalization
        // in dispense()).
        $stock = DrugStock::whereRaw('LOWER(TRIM(drug_name)) = ?', [strtolower(trim($request->input('drug_name')))])->first()
            ?? new DrugStock(['drug_name' => $request->input('drug_name')]);
        $stock->quantity_on_hand = $request->input('quantity_on_hand', $stock->quantity_on_hand ?? 0);
        $stock->reorder_level = $request->input('reorder_level', $stock->reorder_level ?? 10);
        $stock->unit = $request->input('unit', $stock->unit ?? 'tablets');
        $stock->is_controlled = $request->input('is_controlled', $stock->is_controlled ?? false);
        $stock->save();

        AuditLogger::log($request->user(), 'update_stock', 'drug_stock', $stock->id, $request->input('drug_name'));

        return response()->json($stock);
    }
}