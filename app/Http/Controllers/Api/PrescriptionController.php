<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionRequest;
use App\Models\Encounter;
use App\Models\Prescription;
use App\Services\AllergyChecker;

class PrescriptionController extends Controller
{
    public function __construct(private AllergyChecker $allergyChecker) {}

    /**
     * GET /api/encounters/{encounter}/prescriptions
     */
    public function index(Encounter $encounter)
    {
        return response()->json($encounter->prescriptions()->with('dispensingRecords')->get());
    }

    /**
     * POST /api/encounters/{encounter}/prescriptions
     * Clinician prescribes a drug. We surface an allergy warning in the
     * response but do NOT block creation — the clinician is the one
     * qualified to weigh it; the harder stop happens at dispensing time.
     */
    public function store(StorePrescriptionRequest $request, Encounter $encounter)
    {
        $data = $request->validated();
        $data['encounter_id'] = $encounter->id;
        $data['patient_id'] = $encounter->patient_id;
        $data['prescribed_by'] = $request->user()->id;
        $data['status'] = 'pending';

        $prescription = Prescription::create($data);

        $conflicts = $this->allergyChecker->conflictingAllergies($encounter->patient, $data['drug_name']);

        return response()->json([
            ...$prescription->toArray(),
            'allergy_warning' => $conflicts->isNotEmpty() ? $conflicts : null,
        ], 201);
    }
}
