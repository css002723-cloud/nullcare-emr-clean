<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDispensingRequest;
use App\Models\DispensingRecord;
use App\Models\Prescription;
use App\Models\SystemAlert;
use App\Services\AllergyChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensingController extends Controller
{
    public function __construct(private AllergyChecker $allergyChecker) {}

    /**
     * POST /api/prescriptions/{prescription}/dispense
     * The hard stop for allergy safety: unless the pharmacist explicitly
     * passes override_allergy_warning=true, a conflicting allergen blocks
     * the dispense outright (422) rather than just warning.
     */
    public function store(StoreDispensingRequest $request, Prescription $prescription)
    {
        if ($prescription->status === 'cancelled') {
            throw ValidationException::withMessages([
                'prescription' => ['This prescription has been cancelled and cannot be dispensed.'],
            ]);
        }

        $conflicts = $this->allergyChecker->conflictingAllergies($prescription->patient, $prescription->drug_name);

        if ($conflicts->isNotEmpty() && ! $request->boolean('override_allergy_warning')) {
            SystemAlert::create([
                'type' => 'allergy_conflict',
                'message' => "Blocked dispense: {$prescription->drug_name} conflicts with a recorded allergy for patient #{$prescription->patient_id}",
                'severity' => 'critical',
                'is_resolved' => false,
            ]);

            return response()->json([
                'message' => 'This drug conflicts with a recorded patient allergy. Confirm with the prescriber before dispensing.',
                'allergy_conflicts' => $conflicts,
            ], 422);
        }

        $record = DB::transaction(function () use ($request, $prescription) {
            $data = $request->validated();

            $record = DispensingRecord::create([
                'prescription_id' => $prescription->id,
                'dispensed_by' => $request->user()->id,
                'quantity_dispensed' => $data['quantity_dispensed'],
                'dispensed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $prescription->update([
                'status' => $data['final'] ? 'dispensed' : 'partially_dispensed',
            ]);

            return $record;
        });

        return response()->json($record, 201);
    }
}
