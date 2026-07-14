<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVitalSignRequest;
use App\Models\Encounter;
use App\Models\VitalSign;
use App\Services\VitalSignAnalyzer;

class VitalSignController extends Controller
{
    public function __construct(private VitalSignAnalyzer $analyzer) {}

    /**
     * GET /api/encounters/{encounter}/vital-signs
     */
    public function index(Encounter $encounter)
    {
        return response()->json($encounter->vitalSigns()->latest('recorded_at')->get());
    }

    /**
     * POST /api/encounters/{encounter}/vital-signs
     * Triage nurse (or anyone recording obs during the encounter) submits
     * a reading; abnormal flag is computed server-side so it can't be
     * skipped or faked by the client.
     */
    public function store(StoreVitalSignRequest $request, Encounter $encounter)
    {
        $data = $request->validated();
        $data['encounter_id'] = $encounter->id;
        $data['recorded_by'] = $request->user()->id;
        $data['recorded_at'] = now();

        $vital = new VitalSign($data);
        $vital->is_abnormal = $this->analyzer->isAbnormal($vital);
        $vital->save();

        return response()->json($vital, 201);
    }
}
