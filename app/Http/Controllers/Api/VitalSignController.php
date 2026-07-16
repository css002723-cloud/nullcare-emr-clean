<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VitalSignResource;
use App\Models\Encounter;
use App\Models\VitalSign;
use App\Services\VitalSignAnalyzer;
use Illuminate\Http\Request;

class VitalSignController extends Controller
{
    public function __construct(private VitalSignAnalyzer $analyzer) {}

    /**
     * GET /api/vitals/encounter/{encounter}
     */
    public function indexForEncounter(Encounter $encounter)
    {
        return VitalSignResource::collection($encounter->vitalSigns()->orderBy('recorded_at')->get());
    }

    /**
     * POST /api/vitals
     * Body: { encounter_id, temperature_c, pulse_rate, blood_pressure_systolic,
     *         blood_pressure_diastolic, respiratory_rate, spo2, weight_kg,
     *         height_cm, blood_glucose, pain_score, client_uuid }
     * Recording vitals is also the trigger that moves a fresh encounter
     * from the triage queue into the consultation queue.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'blood_pressure_systolic' => ['nullable', 'integer', 'between:50,260'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'between:30,180'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,60'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,400'],
            'height_cm' => ['nullable', 'numeric', 'between:0,250'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0,40'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        $encounter = Encounter::findOrFail($validated['encounter_id']);

        $vital = new VitalSign([
            'encounter_id' => $encounter->id,
            'recorded_by' => $request->user()->id,
            'temperature' => $validated['temperature_c'] ?? null,
            'pulse_rate' => $validated['pulse_rate'] ?? null,
            'blood_pressure_systolic' => $validated['blood_pressure_systolic'] ?? null,
            'blood_pressure_diastolic' => $validated['blood_pressure_diastolic'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'oxygen_saturation' => $validated['spo2'] ?? null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'height_cm' => $validated['height_cm'] ?? null,
            'blood_glucose' => $validated['blood_glucose'] ?? null,
            'pain_score' => $validated['pain_score'] ?? null,
            'recorded_at' => now(),
        ]);

        $vital->early_warning_score = $this->analyzer->earlyWarningScore($vital);
        $vital->is_abnormal = $this->analyzer->isAbnormal($vital);
        $vital->save();

        // Recording vitals is what moves a patient from the triage queue
        // into the consultation queue, per Triage.jsx's own description.
        if ($encounter->stage === 'triage') {
            $encounter->update(['stage' => 'consultation', 'current_department' => 'consultation']);
        }

        return response()->json(new VitalSignResource($vital), 201);
    }
}
