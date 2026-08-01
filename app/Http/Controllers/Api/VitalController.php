<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Vital;
use App\Services\AuditLogger;
use App\Services\ClinicalDecisionSupport;
use Illuminate\Http\Request;

class VitalController extends Controller
{
    public function __construct(private ClinicalDecisionSupport $cds) {}

    /**
     * GET /api/vitals/encounter/{encounter}
     */
    public function indexForEncounter(Encounter $encounter)
    {
        $vitals = Vital::where('encounter_id', $encounter->id)->orderBy('created_at')->get();

        return response()->json($vitals);
    }

    /**
     * POST /api/vitals
     * Roles: nurse, doctor, admin
     */
    public function store(Request $request)
    {
        if (! $request->filled('encounter_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id is required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $patient = Patient::findOrFail($encounter->patient_id);

        $weight = $request->input('weight_kg');
        $height = $request->input('height_cm');
        $bmi = null;
        if ($weight && $height) {
            $heightM = $height / 100;
            $bmi = $heightM > 0 ? round($weight / ($heightM * $heightM), 1) : null;
        }

        [$ews, $flags] = $this->cds->computeEarlyWarningScore($request->all());

        // Pediatric-adjusted alert: kids' "normal" vitals differ significantly from adults.
        if ($patient->isPediatric()) {
            $flags[] = 'Pediatric patient — apply age-adjusted vital sign ranges, not adult thresholds';
        }

        $vital = Vital::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'temperature_c' => $request->input('temperature_c'),
            'blood_pressure_systolic' => $request->input('blood_pressure_systolic'),
            'blood_pressure_diastolic' => $request->input('blood_pressure_diastolic'),
            'pulse_rate' => $request->input('pulse_rate'),
            'respiratory_rate' => $request->input('respiratory_rate'),
            'spo2' => $request->input('spo2'),
            'weight_kg' => $weight,
            'height_cm' => $height,
            'bmi' => $bmi,
            'pain_score' => $request->input('pain_score'),
            'blood_glucose' => $request->input('blood_glucose'),
            'gcs' => $request->input('gcs'),
            'early_warning_score' => $ews,
            'is_abnormal' => $ews >= 3,
            'abnormal_flags' => json_encode($flags),
            'recorded_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        // Auto-advance the journey: registered/triage -> waiting_consultation
        // once vitals are taken.
        if (in_array($encounter->stage, ['registered', 'triage'], true)) {
            $encounter->update(['stage' => 'waiting_consultation', 'current_department' => 'consultation']);
        }

        AuditLogger::log($request->user(), 'record_vitals', 'encounter', $encounter->id, "ews={$ews}");

        $result = $vital->toArray();
        $result['escalation_required'] = $ews >= 5;

        return response()->json($result, 201);
    }
}
