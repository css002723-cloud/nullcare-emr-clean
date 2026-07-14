<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DischargeRequest;
use App\Http\Requests\StoreAdmissionRequest;
use App\Models\Admission;
use App\Models\Encounter;
use Illuminate\Support\Facades\DB;

class AdmissionController extends Controller
{
    /**
     * POST /api/encounters/{encounter}/admission
     * Admits the patient from this encounter into a ward/bed. Flips the
     * encounter status to 'admitted' so it drops off the open-OPD queue.
     */
    public function store(StoreAdmissionRequest $request, Encounter $encounter)
    {
        $admission = DB::transaction(function () use ($request, $encounter) {
            $data = $request->validated();
            $data['patient_id'] = $encounter->patient_id;
            $data['encounter_id'] = $encounter->id;
            $data['admitted_by'] = $request->user()->id;
            $data['admitted_at'] = now();

            $admission = Admission::create($data);

            $encounter->update(['status' => 'admitted']);

            return $admission;
        });

        return response()->json($admission, 201);
    }

    /**
     * PATCH /api/admissions/{admission}/discharge
     */
    public function discharge(DischargeRequest $request, Admission $admission)
    {
        $admission->update([
            ...$request->validated(),
            'discharged_at' => now(),
        ]);

        $admission->encounter?->update(['status' => 'discharged']);

        return response()->json($admission);
    }
}
