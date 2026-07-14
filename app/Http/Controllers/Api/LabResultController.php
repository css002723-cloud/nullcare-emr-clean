<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabResultRequest;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\SystemAlert;
use Illuminate\Support\Facades\DB;

class LabResultController extends Controller
{
    /**
     * POST /api/lab-orders/{labOrder}/result
     * Lab tech enters the result. Marking is_critical=true both flags the
     * result for the clinician's view and raises a system_alert so it
     * surfaces on the admin/clinical dashboard immediately.
     */
    public function store(StoreLabResultRequest $request, LabOrder $labOrder)
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data, $request, $labOrder) {
            $data['lab_order_id'] = $labOrder->id;
            $data['entered_by'] = $request->user()->id;
            $data['result_date'] = now();

            $result = LabResult::create($data);

            $labOrder->update(['status' => 'completed']);

            if ($result->is_critical) {
                SystemAlert::create([
                    'type' => 'critical_result',
                    'message' => "Critical lab result for order #{$labOrder->id} ({$labOrder->test_name})",
                    'severity' => 'critical',
                    'is_resolved' => false,
                ]);
            }

            return $result;
        });

        return response()->json($result, 201);
    }

    /**
     * PATCH /api/lab-results/{labResult}/verify
     * A second clinician/lab lead co-signs the result before it's
     * considered final — common requirement for critical values.
     */
    public function verify(LabResult $labResult, \Illuminate\Http\Request $request)
    {
        $labResult->update(['verified_by' => $request->user()->id]);

        return response()->json($labResult);
    }
}
