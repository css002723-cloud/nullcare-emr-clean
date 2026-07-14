<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEncounterRequest;
use App\Http\Requests\UpdateEncounterRequest;
use App\Models\Encounter;
use App\Models\Patient;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
    /**
     * GET /api/patients/{patient}/encounters
     * Visit history for a patient (most recent first).
     */
    public function index(Patient $patient)
    {
        $encounters = $patient->encounters()
            ->with(['clinician:id,full_name', 'department:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json($encounters);
    }

    /**
     * POST /api/encounters
     * Opens a new encounter — the clinical episode a clinician works within
     * for the rest of the visit (vitals, labs, prescriptions all attach here).
     */
    public function store(StoreEncounterRequest $request)
    {
        $data = $request->validated();
        $data['clinician_id'] = $request->user()->id;
        $data['status'] = 'open';

        $encounter = Encounter::create($data);

        // If this encounter came from a checked-in appointment, move the
        // appointment along so it drops off the waiting-room queue view.
        if ($encounter->appointment_id) {
            $encounter->appointment()->update(['status' => 'completed']);
        }

        return response()->json($this->withSafetyContext($encounter), 201);
    }

    /**
     * GET /api/encounters/{encounter}
     * Full clinical picture for this visit, plus the patient's allergy
     * list surfaced up front — patient safety first, per the design brief.
     */
    public function show(Encounter $encounter)
    {
        $encounter->load([
            'patient:id,patient_number,first_name,last_name,gender,date_of_birth',
            'clinician:id,full_name',
            'department:id,name',
            'vitalSigns',
            'labOrders.result',
            'prescriptions',
        ]);

        return response()->json($this->withSafetyContext($encounter));
    }

    /**
     * PATCH /api/encounters/{encounter}
     * Clinician fills in history / examination / diagnosis / plan as the
     * consult progresses — supports partial updates for autosave.
     */
    public function update(UpdateEncounterRequest $request, Encounter $encounter)
    {
        $encounter->update($request->validated());

        return response()->json($this->withSafetyContext($encounter->fresh()));
    }

    /**
     * POST /api/encounters/{encounter}/close
     * Ends the clinical episode once the treatment plan, billing, and
     * discharge/admission decision are all settled.
     */
    public function close(Request $request, Encounter $encounter)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:closed,referred,admitted,discharged'],
        ]);

        $encounter->update(['status' => $validated['status']]);

        return response()->json($this->withSafetyContext($encounter->fresh()));
    }

    /**
     * Attaches the patient's active allergies to the response so the
     * frontend can render an alert banner without a second API call.
     */
    private function withSafetyContext(Encounter $encounter): array
    {
        $allergies = $encounter->patient
            ? $encounter->patient->allergies()->get(['allergen', 'reaction', 'severity'])
            : collect();

        return [
            ...$encounter->toArray(),
            'safety_alerts' => [
                'allergies' => $allergies,
            ],
        ];
    }
}
