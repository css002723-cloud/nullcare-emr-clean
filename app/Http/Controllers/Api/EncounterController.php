<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\PatientResource;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\LabResult;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
    /**
     * GET /api/encounters?department=&stage=&active_only=true
     * Department worklist / queue. Emergency-flagged encounters always
     * sort to the top, regardless of arrival order.
     */
    public function index(Request $request)
    {
        $query = Encounter::query();

        if ($request->filled('department')) {
            $query->where('current_department', $request->query('department'));
        }
        if ($request->filled('stage')) {
            $query->where('stage', $request->query('stage'));
        }
        if ($request->query('active_only', 'true') === 'true') {
            $query->whereNotIn('stage', Encounter::CLOSED_STAGES);
        }

        $encounters = $query->orderByDesc('is_emergency')->orderByDesc('created_at')->limit(200)->get();

        $encounterIds = $encounters->pluck('id');
        $criticalEncounterIds = LabResult::where('is_critical', true)
            ->where('critical_alert_acknowledged', false)
            ->whereHas('labOrder', fn ($q) => $q->whereIn('encounter_id', $encounterIds))
            ->get()
            ->map(fn ($result) => $result->labOrder?->encounter_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $result = $encounters->map(function (Encounter $e) use ($criticalEncounterIds) {
            $d = (new EncounterResource($e))->toArray(request());
            $patient = Patient::find($e->patient_id);
            $d['patient'] = $patient ? (new PatientResource($patient))->toArray(request()) : null;
            $d['referral'] = in_array($e->stage, Encounter::CLOSED_STAGES, true) ? null : $e->activeReferralSummary();
            $d['has_critical_lab_alert'] = in_array($e->id, $criticalEncounterIds, true);

            return $d;
        });

        return response()->json($result);
    }

    /**
     * GET /api/encounters/{encounter}
     */
    public function show(Encounter $encounter)
    {
        $d = (new EncounterResource($encounter))->toArray(request());
        $patient = Patient::find($encounter->patient_id);
        $d['patient'] = $patient ? (new PatientResource($patient))->toArray(request()) : null;
        $d['referral'] = in_array($encounter->stage, Encounter::CLOSED_STAGES, true) ? null : $encounter->activeReferralSummary();

        return response()->json($d);
    }

    /**
     * GET /api/encounters/by-mrn/{mrn}
     * MRN identifies a specific VISIT, not a person.
     */
    public function showByMrn(string $mrn)
    {
        $encounter = Encounter::where('mrn', strtoupper(trim($mrn)))->first();

        if (! $encounter) {
            return response()->json(['error' => 'not_found', 'message' => "No visit found with MRN {$mrn}"], 404);
        }

        return $this->show($encounter);
    }

    /**
     * POST /api/encounters
     * Roles: reception, nurse, admin
     * Opens a new encounter for an EXISTING patient — a returning patient
     * is never re-registered, just resolved (by patient_uid or search)
     * and passed in here. Every encounter gets a fresh MRN, since MRN is
     * per-visit, not permanent (see Patient.patient_uid for that).
     */
    public function store(Request $request)
    {
        if (! $request->filled('patient_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'patient_id is required'], 400);
        }

        $patient = Patient::findOrFail($request->input('patient_id'));

        do {
            $mrn = IdGenerator::mrn();
        } while (Encounter::where('mrn', $mrn)->exists());

        $visitType = $request->input('visit_type', 'outpatient');
        $priority = $request->input('priority', 'routine');
        $isEmergency = $visitType === 'emergency' || $priority === 'emergency';

        $encounter = Encounter::create([
            'encounter_number' => IdGenerator::encounterNumber(),
            'mrn' => $mrn,
            'patient_id' => $patient->id,
            'visit_type' => $visitType,
            'stage' => 'registered',
            'priority' => $priority,
            'is_emergency' => $isEmergency,
            'chief_complaint' => $request->input('chief_complaint'),
            'current_department' => $isEmergency ? 'emergency' : 'triage',
            'registered_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log(
            $request->user(), 'create_encounter', 'encounter', $encounter->id,
            "patient_id={$patient->id} mrn={$mrn}".($isEmergency ? ' EMERGENCY' : '')
        );

        $d = (new EncounterResource($encounter))->toArray($request);
        $d['patient'] = new PatientResource($patient);

        return response()->json($d, 201);
    }

    /**
     * POST /api/encounters/{encounter}/transition
     * The core hand-off mechanism — moves stage/department/clinician/
     * priority/ward/bed. e.g. nurse completes triage -> stage=
     * waiting_consultation, department=consultation; doctor refers to lab
     * -> department=laboratory (encounter stays in_consultation).
     */
    public function transition(Request $request, Encounter $encounter)
    {
        if ($request->has('stage')) {
            $encounter->stage = $request->input('stage');
        }
        if ($request->has('current_department')) {
            $encounter->current_department = $request->input('current_department');
        }
        if ($request->has('assigned_clinician_id')) {
            $encounter->assigned_clinician_id = $request->input('assigned_clinician_id');
        }
        if ($request->has('priority')) {
            $encounter->priority = $request->input('priority');
            $encounter->is_emergency = $request->input('priority') === 'emergency' || $encounter->visit_type === 'emergency';
        }
        if ($request->has('ward')) {
            $encounter->ward = $request->input('ward');
        }
        if ($request->has('bed')) {
            $encounter->bed = $request->input('bed');
        }

        $encounter->save();

        AuditLogger::log($request->user(), 'transition_encounter', 'encounter', $encounter->id, json_encode($request->all()));

        return new EncounterResource($encounter);
    }

    /**
     * POST /api/encounters/{encounter}/close
     * Roles: doctor, nurse, reception, admin
     * outcome: discharged | admitted | referred_out | died | cancelled
     * Reception may only use "cancelled" — a mistaken registration or
     * no-show, distinct from a clinical discharge since it doesn't imply
     * the patient was ever actually seen. Clinical dispositions stay
     * restricted to clinical staff.
     */
    public function close(Request $request, Encounter $encounter)
    {
        $validOutcomes = ['discharged', 'admitted', 'referred_out', 'died', 'cancelled'];
        $outcome = $request->input('outcome');

        if (! in_array($outcome, $validOutcomes, true)) {
            return response()->json(['error' => 'invalid_outcome', 'message' => 'outcome must be one of: '.implode(', ', $validOutcomes)], 400);
        }

        if ($request->user()->role === 'reception' && $outcome !== 'cancelled') {
            return response()->json(['error' => 'forbidden', 'message' => 'Reception can only cancel a registration, not record a clinical disposition.'], 403);
        }

        $encounter->outcome = $outcome;
        $encounter->disposition_notes = $request->input('disposition_notes');
        $encounter->stage = match ($outcome) {
            'died' => 'deceased',
            'admitted' => 'admitted',
            'cancelled' => 'cancelled',
            default => 'discharged',
        };
        $encounter->closed_at = now();

        if ($outcome === 'died') {
            $patient = Patient::find($encounter->patient_id);
            $patient->update(['is_deceased' => true, 'date_of_death' => now()]);

            ClinicalNote::create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'note_type' => 'death',
                'body' => $request->input('disposition_notes', ''),
                'author_id' => $request->user()->id,
                'author_role' => $request->user()->role,
            ]);
        }

        $encounter->save();

        AuditLogger::log($request->user(), 'close_encounter', 'encounter', $encounter->id, $outcome);

        return new EncounterResource($encounter);
    }

    /**
     * GET /api/encounters/note-templates
     */
    public function noteTemplates()
    {
        return response()->json(ClinicalNote::TEMPLATES);
    }
}
