<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * GET /api/orders?encounter_id=&target_department=&status=
     */
    public function index(Request $request)
    {
        $query = Order::query();

        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }
        if ($request->filled('target_department')) {
            $query->where('target_department', $request->query('target_department'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        if (! $request->filled('encounter_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id is required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $orderType = $request->input('order_type', 'procedure');

        $order = DB::transaction(function () use ($request, $encounter, $orderType) {
            $order = Order::create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'order_type' => $orderType,
                'details' => $request->input('details'),
                'priority' => $request->input('priority', 'routine'),
                'target_department' => $request->input('target_department'),
                'ordered_by' => $request->user()->id,
                'client_uuid' => $request->input('client_uuid'),
            ]);

            if ($orderType === 'lab' && $request->filled('details')) {
                $testCode = strtoupper(trim($request->input('details')));
                $loinc = LabOrder::CATALOG[$testCode] ?? [];

                LabOrder::create([
                    'order_id' => $order->id,
                    'encounter_id' => $encounter->id,
                    'patient_id' => $encounter->patient_id,
                    'test_code' => $testCode,
                    'loinc_code' => $loinc['loinc_code'] ?? null,
                    'loinc_display' => $loinc['loinc_display'] ?? null,
                    'barcode' => IdGenerator::barcode(),
                    'priority' => $request->input('priority', 'routine'),
                    'ordered_by' => $request->user()->id,
                ]);
            }

            return $order;
        });

        AuditLogger::log(
            $request->user(), 'create_order', 'encounter', $encounter->id,
            "type={$order->order_type} dept={$order->target_department}"
        );

        return response()->json($order, 201);
    }

    /**
     * POST /api/orders/{order}/acknowledge
     */
    public function acknowledge(Request $request, Order $order)
    {
        $order->update([
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
            'status' => 'in_progress',
        ]);

        AuditLogger::log($request->user(), 'acknowledge_order', 'order', $order->id);

        return response()->json($order);
    }

    /**
     * PUT /api/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $order->update(['status' => $request->input('status', $order->status)]);

        AuditLogger::log($request->user(), 'update_order_status', 'order', $order->id, $order->status);

        return response()->json($order);
    }
}