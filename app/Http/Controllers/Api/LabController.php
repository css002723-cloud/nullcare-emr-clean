<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LabOrderResource;
use App\Http\Resources\LabResultResource;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTestCatalog;
use App\Models\SystemAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabController extends Controller
{
    /**
     * GET /api/lab/catalog
     * Returns an object keyed by test_code, e.g. { "FBC": { "loinc_code": "...", "loinc_display": "..." } },
     * matching the shape QuickOrderPanel in Laboratory.jsx expects (not an array).
     */
    public function catalog()
    {
        $catalog = LabTestCatalog::all()->keyBy('test_code')->map(fn ($t) => [
            'loinc_code' => $t->loinc_code,
            'loinc_display' => $t->loinc_display,
            'default_specimen_type' => $t->default_specimen_type,
        ]);

        return response()->json($catalog);
    }

    /**
     * GET /api/lab/orders?status=
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        // Frontend's status tabs use "resulted" as the terminal state where
        // the database stores "completed" — translate at the query boundary.
        $internalStatus = $status === 'resulted' ? 'completed' : $status;

        $orders = LabOrder::query()
            ->with(['result', 'catalogEntry'])
            ->when($internalStatus, fn ($q) => $q->where('status', $internalStatus))
            ->latest('ordered_at')
            ->get();

        return LabOrderResource::collection($orders);
    }

    /**
     * POST /api/lab/orders
     * Body: { encounter_id, test_code, specimen_type, priority }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'test_code' => ['required', 'string', 'exists:lab_test_catalog,test_code'],
            'specimen_type' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'in:routine,urgent,stat'],
        ]);

        $encounter = Encounter::findOrFail($validated['encounter_id']);
        $catalogEntry = LabTestCatalog::find($validated['test_code']);

        $order = LabOrder::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'ordered_by' => $request->user()->id,
            'test_name' => $catalogEntry->loinc_display,
            'test_code' => $validated['test_code'],
            'loinc_code' => $catalogEntry->loinc_code,
            'specimen_type' => $validated['specimen_type'] ?? $catalogEntry->default_specimen_type,
            'barcode' => 'LAB-'.strtoupper(Str::random(8)),
            'status' => 'ordered',
            'urgency' => $validated['priority'],
            'ordered_at' => now(),
        ]);

        return response()->json(new LabOrderResource($order->load('catalogEntry')), 201);
    }

    /**
     * POST /api/lab/orders/{labOrder}/collect
     */
    public function collect(LabOrder $labOrder)
    {
        $labOrder->update(['status' => 'collected']);

        return new LabOrderResource($labOrder->load('catalogEntry', 'result'));
    }

    /**
     * POST /api/lab/orders/{labOrder}/receive
     */
    public function receive(LabOrder $labOrder)
    {
        $labOrder->update(['status' => 'received']);

        return new LabOrderResource($labOrder->load('catalogEntry', 'result'));
    }

    /**
     * POST /api/lab/orders/{labOrder}/result
     * Body: { result_value, unit, reference_range, is_critical, is_abnormal, interpretation }
     */
    public function storeResult(Request $request, LabOrder $labOrder)
    {
        $validated = $request->validate([
            'result_value' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:100'],
            'interpretation' => ['nullable', 'string'],
            'is_critical' => ['required', 'boolean'],
            'is_abnormal' => ['required', 'boolean'],
        ]);

        $result = DB::transaction(function () use ($validated, $request, $labOrder) {
            $result = LabResult::create([
                ...$validated,
                'lab_order_id' => $labOrder->id,
                'entered_by' => $request->user()->id,
                'result_date' => now(),
            ]);

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

        return response()->json(new LabResultResource($result), 201);
    }

    /**
     * GET /api/lab/critical-unacknowledged
     * Consultation.jsx only reads the array length, so any array shape works.
     */
    public function criticalUnacknowledged()
    {
        $results = LabResult::where('is_critical', true)->whereNull('verified_by')->get();

        return LabResultResource::collection($results);
    }
}
