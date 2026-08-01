<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DialysisSession;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DialysisController extends Controller
{
    /**
     * GET /api/dialysis/patients
     * Roster of every patient with dialysis history — a patient-centric
     * view so staff can pick up a known CKD patient without needing to
     * remember their (per-visit, regenerated) MRN.
     */
    public function listPatients()
    {
        $patientIds = DialysisSession::distinct()->pluck('patient_id');

        $result = $patientIds->map(function ($pid) {
            $patient = Patient::find($pid);
            if (! $patient) {
                return null;
            }

            $sessions = DialysisSession::where('patient_id', $pid)->orderByDesc('session_date')->get();
            $missed = $sessions->where('status', 'missed')->count();
            $latest = $sessions->first();

            return [
                'patient_id' => $pid,
                'patient_uid' => $patient->patient_uid,
                'full_name' => "{$patient->given_name} {$patient->family_name}",
                'total_sessions' => $sessions->count(),
                'missed_sessions' => $missed,
                'latest_session_date' => $latest?->session_date?->toIso8601String(),
                'latest_status' => $latest?->status,
                'ckd_stage' => $latest?->ckd_stage,
            ];
        })->filter()->sortByDesc('latest_session_date')->values();

        return response()->json($result);
    }

    /**
     * GET /api/dialysis/sessions?patient_id=&status=
     */
    public function index(Request $request)
    {
        $query = DialysisSession::query();

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $sessions = $query->orderByDesc('session_date')->get();

        $result = $sessions->map(function (DialysisSession $s) {
            $d = $s->toArray();
            $patient = Patient::find($s->patient_id);
            $d['patient_name'] = $patient ? "{$patient->given_name} {$patient->family_name}" : null;
            $d['patient_uid'] = $patient?->patient_uid;

            return $d;
        });

        return response()->json($result);
    }

    /**
     * POST /api/dialysis/sessions
     * Roles: dialysis_tech, doctor, nurse, admin
     */
    public function store(Request $request)
    {
        if (! $request->filled('patient_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'patient_id is required'], 400);
        }

        $patient = Patient::findOrFail($request->input('patient_id'));

        $sessionDate = now();
        if ($request->filled('session_date')) {
            try {
                $sessionDate = Carbon::parse($request->input('session_date'));
            } catch (\Exception $e) {
                return response()->json(['error' => 'invalid_date', 'message' => 'session_date must be a valid ISO datetime'], 400);
            }
        }

        $session = DialysisSession::create([
            'patient_id' => $patient->id,
            'encounter_id' => $request->input('encounter_id'),
            'ckd_stage' => $request->input('ckd_stage'),
            'session_date' => $sessionDate,
            'pre_weight_kg' => $request->input('pre_weight_kg'),
            'post_weight_kg' => $request->input('post_weight_kg'),
            'fluid_removal_target_l' => $request->input('fluid_removal_target_l'),
            'vascular_access_type' => $request->input('vascular_access_type'),
            'complications' => $request->input('complications'),
            'status' => $request->input('status', 'scheduled'),
            'performed_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log($request->user(), 'create_dialysis_session', 'patient', $patient->id);

        return response()->json($session, 201);
    }

    /**
     * PUT /api/dialysis/sessions/{dialysisSession}
     * Roles: dialysis_tech, doctor, nurse, admin
     */
    public function update(Request $request, DialysisSession $dialysisSession)
    {
        foreach (['post_weight_kg', 'complications', 'status'] as $field) {
            if ($request->has($field)) {
                $dialysisSession->{$field} = $request->input($field);
            }
        }
        $dialysisSession->save();

        AuditLogger::log($request->user(), 'update_dialysis_session', 'dialysis_session', $dialysisSession->id);

        return response()->json($dialysisSession);
    }

    /**
     * GET /api/dialysis/dashboard/{patient}
     * Longitudinal chronic-care dashboard for one CKD/dialysis patient.
     */
    public function patientDashboard(Patient $patient)
    {
        $sessions = DialysisSession::where('patient_id', $patient->id)->orderBy('session_date')->get();
        $missed = $sessions->where('status', 'missed')->count();

        return response()->json([
            'patient_id' => $patient->id,
            'total_sessions' => $sessions->count(),
            'missed_sessions' => $missed,
            'sessions' => $sessions,
        ]);
    }
}
