<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabController extends Controller
{
    /**
     * GET /api/lab/catalog
     */
    public function catalog()
    {
        return response()->json(LabOrder::CATALOG);
    }

    /**
     * GET /api/lab/orders?status=&encounter_id=
     */
    public function index(Request $request)
    {
        $query = LabOrder::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }

        $orders = $query->latest()->get();

        $result = $orders->map(function (LabOrder $o) {
            $d = $o->toArray();
            $d['patient_name'] = $o->patient?->full_name;
            $d['result'] = LabResult::where('lab_order_id', $o->id)->first();

            return $d;
        });

        return response()->json($result);
    }

    /**
     * POST /api/lab/orders
     * Roles: doctor, nurse, admin
     * Also creates the parent generic Order row (order_type=lab,
     * target_department=laboratory) — every lab order is a specialization
     * of a generic order, matching the reference's dual-table design.
     */
    public function store(Request $request)
    {
        if (! $request->filled('encounter_id') || ! $request->filled('test_code')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id and test_code are required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $loinc = LabOrder::CATALOG[$request->input('test_code')] ?? [];

        $labOrder = DB::transaction(function () use ($request, $encounter, $loinc) {
            $genericOrder = Order::create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'order_type' => 'lab',
                'details' => $request->input('test_code'),
                'priority' => $request->input('priority', 'routine'),
                'target_department' => 'laboratory',
                'ordered_by' => $request->user()->id,
            ]);

            return LabOrder::create([
                'order_id' => $genericOrder->id,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'test_code' => $request->input('test_code'),
                'loinc_code' => $loinc['loinc_code'] ?? null,
                'loinc_display' => $loinc['loinc_display'] ?? null,
                'specimen_type' => $request->input('specimen_type'),
                'barcode' => IdGenerator::barcode(),
                'priority' => $request->input('priority', 'routine'),
                'ordered_by' => $request->user()->id,
                'client_uuid' => $request->input('client_uuid'),
            ]);
        });

        AuditLogger::log($request->user(), 'order_lab_test', 'encounter', $encounter->id, $request->input('test_code'));

        return response()->json($labOrder, 201);
    }

    /**
     * POST /api/lab/orders/{labOrder}/collect
     * Roles: lab_tech, nurse, admin
     */
    public function collect(Request $request, LabOrder $labOrder)
    {
        $labOrder->update([
            'status' => 'collected',
            'collected_by' => $request->user()->id,
            'collected_at' => now(),
        ]);

        AuditLogger::log($request->user(), 'collect_specimen', 'lab_order', $labOrder->id);

        return response()->json($labOrder);
    }

    /**
     * POST /api/lab/orders/{labOrder}/receive
     * Roles: lab_tech, admin
     */
    public function receive(Request $request, LabOrder $labOrder)
    {
        $labOrder->update(['status' => 'received', 'received_at' => now()]);

        AuditLogger::log($request->user(), 'receive_specimen', 'lab_order', $labOrder->id);

        return response()->json($labOrder);
    }

    /**
     * POST /api/lab/orders/{labOrder}/result
     * Roles: lab_tech, admin
     */
    public function storeResult(Request $request, LabOrder $labOrder)
    {
        $result = LabResult::create([
            'lab_order_id' => $labOrder->id,
            'result_value' => $request->input('result_value'),
            'unit' => $request->input('unit'),
            'reference_range' => $request->input('reference_range'),
            'is_critical' => $request->input('is_critical', false),
            'is_abnormal' => $request->input('is_abnormal', false),
            'interpretation' => $request->input('interpretation'),
            'entered_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        $labOrder->update(['status' => 'resulted']);

        AuditLogger::log($request->user(), 'enter_lab_result', 'lab_order', $labOrder->id, 'critical='.($result->is_critical ? 'true' : 'false'));

        return response()->json($result, 201);
    }

    /**
     * POST /api/lab/results/{labResult}/verify
     * Roles: lab_tech, admin
     */
    public function verify(Request $request, LabResult $labResult)
    {
        $labResult->update(['verified_by' => $request->user()->id, 'verified_at' => now()]);
        LabOrder::where('id', $labResult->lab_order_id)->update(['status' => 'verified']);

        AuditLogger::log($request->user(), 'verify_lab_result', 'lab_result', $labResult->id);

        return response()->json($labResult);
    }

    /**
     * POST /api/lab/results/{labResult}/acknowledge-critical
     * Roles: doctor, nurse, admin
     */
    public function acknowledgeCritical(Request $request, LabResult $labResult)
    {
        $labResult->update(['critical_alert_acknowledged' => true]);

        AuditLogger::log($request->user(), 'acknowledge_critical_result', 'lab_result', $labResult->id);

        return response()->json($labResult);
    }

    /**
     * GET /api/lab/critical-unacknowledged
     */
    public function criticalUnacknowledged()
    {
        return response()->json(LabResult::where('is_critical', true)->where('critical_alert_acknowledged', false)->get());
    }
}
