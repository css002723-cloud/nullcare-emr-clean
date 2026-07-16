<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EncounterResource;
use App\Models\Encounter;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EncounterController extends Controller
{
    /**
     * GET /api/encounters?department=triage|consultation
     * Powers Triage.jsx and Consultation.jsx work queues, filtered by the
     * encounter's current pipeline stage (not a physical hospital department).
     */
    public function index(Request $request)
    {
        $stage = $request->query('department');

        $encounters = Encounter::query()
            ->with('patient')
            ->when($stage, fn ($query) => $query->where('stage', $stage))
            ->whereNotIn('status', ['closed', 'discharged', 'referred_out', 'died'])
            ->latest()
            ->get();

        return EncounterResource::collection($encounters);
    }

    /**
     * POST /api/encounters
     * Body: { patient_id, patient_client_uuid?, visit_type, chief_complaint,
     *         priority, client_uuid }
     * Reception opens the visit immediately after registering the patient —
     * no clinician or department is known yet, hence both are nullable and
     * left blank until triage/consultation picks the encounter up.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required_without:patient_client_uuid', 'nullable', 'integer', 'exists:patients,id'],
            'patient_client_uuid' => ['required_without:patient_id', 'nullable', 'string', 'max:36'],
            'visit_type' => ['required', 'in:outpatient,inpatient,emergency'],
            'chief_complaint' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:routine,urgent,emergency'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (! empty($data['client_uuid'])) {
            $existing = Encounter::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return new EncounterResource($existing->load('patient'));
            }
        }

        $patientId = $data['patient_id'] ?? null;
        if (! $patientId && ! empty($data['patient_client_uuid'])) {
            $patientId = Patient::where('client_uuid', $data['patient_client_uuid'])->value('id');
        }

        if (! $patientId) {
            return response()->json(['message' => 'Could not resolve the patient for this encounter.'], 422);
        }

        $encounter = DB::transaction(function () use ($data, $patientId, $request) {
            return Encounter::create([
                'encounter_number' => $this->generateEncounterNumber(),
                'client_uuid' => $data['client_uuid'] ?? null,
                'patient_id' => $patientId,
                'clinician_id' => $request->user()->id,
                'encounter_type' => $data['visit_type'],
                'presenting_complaint' => $data['chief_complaint'] ?? null,
                'triage_category' => $data['priority'] ?? 'routine',
                'status' => 'open',
                'stage' => 'triage',
                'current_department' => 'triage',
            ]);
        });

        return response()->json(new EncounterResource($encounter->load('patient')), 201);
    }

    /**
     * GET /api/encounters/{encounter}
     */
    public function show(Encounter $encounter)
    {
        $encounter->load([
            'patient.allergies',
            'vitalSigns',
            'labOrders.result',
            'prescriptions',
            'clinicalNotes',
            'clinicalOrders',
            'referrals',
        ]);

        return new EncounterResource($encounter);
    }

    /**
     * POST /api/encounters/{encounter}/close
     * Body: { outcome, disposition_notes }
     * outcome: discharged | admitted | referred_out | died
     */
    public function close(Request $request, Encounter $encounter)
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:discharged,admitted,referred_out,died'],
            'disposition_notes' => ['nullable', 'string'],
        ]);

        $encounter->update([
            'status' => $validated['outcome'],
            'disposition_notes' => $validated['disposition_notes'] ?? null,
            'stage' => 'completed',
        ]);

        return new EncounterResource($encounter->fresh(['patient']));
    }

    private function generateEncounterNumber(): string
    {
        $year = now()->format('Y');

        do {
            $sequence = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $candidate = "ENC-{$year}-{$sequence}";
        } while (Encounter::where('encounter_number', $candidate)->exists());

        return $candidate;
    }
}
