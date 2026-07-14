<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabOrderRequest;
use App\Models\Encounter;
use App\Models\LabOrder;

class LabOrderController extends Controller
{
    /**
     * GET /api/encounters/{encounter}/lab-orders
     */
    public function index(Encounter $encounter)
    {
        return response()->json($encounter->labOrders()->with('result')->latest('ordered_at')->get());
    }

    /**
     * POST /api/encounters/{encounter}/lab-orders
     * Clinician orders a test. patient_id is copied from the encounter so
     * lab staff can query by patient without joining through encounters.
     */
    public function store(StoreLabOrderRequest $request, Encounter $encounter)
    {
        $data = $request->validated();
        $data['encounter_id'] = $encounter->id;
        $data['patient_id'] = $encounter->patient_id;
        $data['ordered_by'] = $request->user()->id;
        $data['status'] = 'ordered';
        $data['ordered_at'] = now();

        $order = LabOrder::create($data);

        return response()->json($order, 201);
    }

    /**
     * PATCH /api/lab-orders/{labOrder}/status
     * Lab tech moves the order through collected -> received -> processing
     * -> completed, so the frontend can show a live specimen-tracking status.
     */
    public function updateStatus(\Illuminate\Http\Request $request, LabOrder $labOrder)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:ordered,collected,received,processing,completed,cancelled'],
        ]);

        $labOrder->update($validated);

        return response()->json($labOrder);
    }
}
