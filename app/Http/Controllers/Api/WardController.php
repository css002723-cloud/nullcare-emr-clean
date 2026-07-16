<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Encounter;
use Illuminate\Http\Request;

class WardController extends Controller
{
    /**
     * GET /api/wards/occupancy
     * Returns: [{ ward, occupied_beds, patients: [{ encounter_id, patient_name, mrn, bed }] }]
     */
    public function occupancy()
    {
        $admissions = Admission::with('patient', 'encounter')
            ->whereNull('discharged_at')
            ->get()
            ->groupBy('ward_name');

        $occupancy = $admissions->map(function ($group, $wardName) {
            return [
                'ward' => $wardName,
                'occupied_beds' => $group->count(),
                'patients' => $group->map(fn ($admission) => [
                    'encounter_id' => $admission->encounter_id,
                    'patient_name' => $admission->patient->full_name,
                    'mrn' => $admission->patient->patient_number,
                    'bed' => $admission->bed_number,
                ])->values(),
            ];
        })->values();

        return response()->json($occupancy);
    }

    /**
     * POST /api/wards/admit
     * Body: { encounter_id, ward, bed }
     * Called by DispositionPanel just before /encounters/{id}/close when
     * the outcome is "admitted".
     */
    public function admit(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'ward' => ['required', 'string', 'max:100'],
            'bed' => ['nullable', 'string', 'max:20'],
        ]);

        $encounter = Encounter::findOrFail($validated['encounter_id']);

        $admission = Admission::create([
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'ward_name' => $validated['ward'],
            'bed_number' => $validated['bed'] ?? null,
            'admitted_by' => $request->user()->id,
            'admitted_at' => now(),
        ]);

        return response()->json($admission, 201);
    }
}
