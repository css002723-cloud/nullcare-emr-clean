<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PharmacyStockResource;
use App\Http\Resources\PrescriptionResource;
use App\Models\Encounter;
use App\Models\PharmacyStock;
use App\Models\Prescription;
use App\Models\SystemAlert;
use App\Services\AllergyChecker;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function __construct(private AllergyChecker $allergyChecker) {}

    /**
     * GET /api/pharmacy/prescriptions?status=pending|dispensed
     */
    public function indexPrescriptions(Request $request)
    {
        $status = $request->query('status', 'pending');

        $prescriptions = Prescription::where('status', $status)->latest()->get();

        return PrescriptionResource::collection($prescriptions);
    }

    /**
     * POST /api/pharmacy/prescriptions
     * Body: { encounter_id, drug_name, formulation, dose, route, frequency, duration }
     * Runs the allergy + pediatric-dose safety checks immediately and stores
     * the result as cds_alerts (JSON string, for the list view later) AND
     * returns cds_alerts_list (a real array, for the immediate on-screen
     * warning right after prescribing) — the frontend reads these two
     * differently in two different places.
     */
    public function storePrescription(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'drug_name' => ['required', 'string', 'max:150'],
            'formulation' => ['nullable', 'string', 'max:100'],
            'dose' => ['nullable', 'string', 'max:50'],
            'route' => ['nullable', 'string', 'max:50'],
            'frequency' => ['nullable', 'string', 'max:50'],
            'duration' => ['nullable', 'string', 'max:50'],
        ]);

        $encounter = Encounter::with('patient.allergies')->findOrFail($validated['encounter_id']);
        $patient = $encounter->patient;

        $alerts = [];

        $conflicts = $this->allergyChecker->conflictingAllergies($patient, $validated['drug_name']);
        foreach ($conflicts as $conflict) {
            $alerts[] = "Allergy alert: patient has a recorded {$conflict->severity} allergy to {$conflict->allergen}.";
        }

        $isPediatric = false;
        $age = $patient->age_estimate ?? ($patient->date_of_birth ? $patient->date_of_birth->age : null);
        if ($age !== null && $age < 12) {
            $isPediatric = true;
            $alerts[] = 'Pediatric patient — verify weight-based dose calculation before dispensing.';
        }

        $prescription = Prescription::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'prescribed_by' => $request->user()->id,
            'drug_name' => $validated['drug_name'],
            'formulation' => $validated['formulation'] ?? null,
            'dose' => $validated['dose'] ?? null,
            'route' => $validated['route'] ?? null,
            'frequency' => $validated['frequency'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'status' => 'pending',
            'is_pediatric_dose' => $isPediatric,
            'cds_alerts' => json_encode($alerts),
        ]);

        if ($conflicts->isNotEmpty()) {
            SystemAlert::create([
                'type' => 'allergy_conflict',
                'message' => "Prescription for {$validated['drug_name']} conflicts with a recorded allergy for patient #{$encounter->patient_id}",
                'severity' => 'critical',
                'is_resolved' => false,
            ]);
        }

        return response()->json([
            ...(new PrescriptionResource($prescription))->toArray($request),
            'cds_alerts_list' => $alerts,
        ], 201);
    }

    /**
     * POST /api/pharmacy/prescriptions/{prescription}/dispense
     * No body — the frontend has no override UI, so rather than hard-block
     * (which the current screen can't recover from), an existing safety
     * conflict is logged as a critical system alert and the dispense still
     * proceeds. This is a deliberate tradeoff given the frontend's current
     * design — flagged clearly in the project notes for the team.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        if ($prescription->status === 'dispensed') {
            return response()->json(['message' => 'This prescription has already been dispensed.'], 422);
        }

        $alerts = json_decode($prescription->cds_alerts ?? '[]', true) ?: [];

        if (! empty($alerts)) {
            SystemAlert::create([
                'type' => 'allergy_conflict',
                'message' => "Dispensed {$prescription->drug_name} for patient #{$prescription->patient_id} despite active safety alert(s): ".implode(' ', $alerts),
                'severity' => 'critical',
                'is_resolved' => false,
            ]);
        }

        $prescription->update(['status' => 'dispensed']);

        return new PrescriptionResource($prescription->fresh());
    }

    /**
     * GET /api/pharmacy/stock
     */
    public function stock()
    {
        return PharmacyStockResource::collection(PharmacyStock::orderBy('drug_name')->get());
    }
}
