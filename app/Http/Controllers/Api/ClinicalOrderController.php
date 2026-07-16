<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicalOrderResource;
use App\Models\ClinicalOrder;
use App\Models\Encounter;
use Illuminate\Http\Request;

class ClinicalOrderController extends Controller
{
    /**
     * GET /api/orders?encounter_id=
     */
    public function index(Request $request)
    {
        $query = ClinicalOrder::query()->latest();

        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }

        return ClinicalOrderResource::collection($query->get());
    }

    /**
     * POST /api/orders
     * Body: { encounter_id, order_type, details, target_department, priority, client_uuid }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'order_type' => ['required', 'in:lab,imaging,procedure,admission'],
            'details' => ['nullable', 'string'],
            'target_department' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'in:routine,urgent,stat'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        if (! empty($validated['client_uuid'])) {
            $existing = ClinicalOrder::where('client_uuid', $validated['client_uuid'])->first();
            if ($existing) {
                return new ClinicalOrderResource($existing);
            }
        }

        Encounter::findOrFail($validated['encounter_id']);

        $order = ClinicalOrder::create([
            ...$validated,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(new ClinicalOrderResource($order), 201);
    }
}
