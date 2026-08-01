<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\InventoryBatch;
use App\Models\InventoryConsumption;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InventoryController extends Controller
{
    /**
     * An item's on-hand quantity is the sum of its batches — keeps
     * batch/expiry tracking and low-stock alerting from ever drifting out
     * of sync with each other, matching _item_summary() in the reference.
     */
    private function itemSummary(InventoryItem $item): array
    {
        $batches = InventoryBatch::where('item_id', $item->id)->get();
        $total = $batches->sum('quantity_on_hand');
        $nearestExpiry = $batches->where('quantity_on_hand', '>', 0)->whereNotNull('expiry_date')->min('expiry_date');

        $d = $item->toArray();
        $d['quantity_on_hand'] = $total;
        $d['low_stock'] = $total <= $item->reorder_level;
        $d['nearest_expiry'] = $nearestExpiry ? Carbon::parse($nearestExpiry)->toDateString() : null;
        $d['batch_count'] = $batches->count();

        return $d;
    }

    public function categories()
    {
        return response()->json(InventoryItem::CATEGORIES);
    }

    /**
     * GET /api/inventory/items?category=
     */
    public function index(Request $request)
    {
        $query = InventoryItem::query();
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $items = $query->orderBy('name')->get();

        return response()->json($items->map(fn ($i) => $this->itemSummary($i)));
    }

    /**
     * GET /api/inventory/items/{inventoryItem}
     */
    public function show(InventoryItem $inventoryItem)
    {
        $d = $this->itemSummary($inventoryItem);
        $d['batches'] = InventoryBatch::where('item_id', $inventoryItem->id)->orderByRaw('expiry_date IS NULL, expiry_date ASC')->get();

        return response()->json($d);
    }

    /**
     * POST /api/inventory/items
     * Roles: pharmacist, lab_tech, radiologist, nurse, dialysis_tech, admin
     */
    public function store(Request $request)
    {
        if (! $request->filled('name') || ! $request->filled('category')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'name and category are required'], 400);
        }

        if (! in_array($request->input('category'), InventoryItem::CATEGORIES, true)) {
            return response()->json(['error' => 'invalid_category', 'message' => 'category must be one of: '.implode(', ', InventoryItem::CATEGORIES)], 400);
        }

        $item = InventoryItem::create([
            'name' => $request->input('name'),
            'category' => $request->input('category'),
            'unit' => $request->input('unit', 'units'),
            'reorder_level' => $request->input('reorder_level', 10),
            'is_controlled' => $request->input('is_controlled', false),
            'department' => $request->input('department'),
            'notes' => $request->input('notes'),
        ]);

        AuditLogger::log($request->user(), 'create_inventory_item', 'inventory_item', $item->id, $item->name);

        return response()->json($this->itemSummary($item), 201);
    }

    /**
     * PUT /api/inventory/items/{inventoryItem}
     */
    public function update(Request $request, InventoryItem $inventoryItem)
    {
        foreach (['name', 'unit', 'reorder_level', 'is_controlled', 'department', 'notes'] as $field) {
            if ($request->has($field)) {
                $inventoryItem->{$field} = $request->input($field);
            }
        }
        $inventoryItem->save();

        AuditLogger::log($request->user(), 'update_inventory_item', 'inventory_item', $inventoryItem->id);

        return response()->json($this->itemSummary($inventoryItem));
    }

    /**
     * POST /api/inventory/items/{inventoryItem}/batches
     * Stock receiving.
     */
    public function receiveBatch(Request $request, InventoryItem $inventoryItem)
    {
        $quantity = (int) $request->input('quantity_received', 0);
        if ($quantity <= 0) {
            return response()->json(['error' => 'invalid_quantity', 'message' => 'quantity_received must be greater than zero'], 400);
        }

        $expiryDate = null;
        if ($request->filled('expiry_date')) {
            try {
                $expiryDate = Carbon::createFromFormat('Y-m-d', $request->input('expiry_date'))->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'invalid_date', 'message' => 'expiry_date must be YYYY-MM-DD'], 400);
            }
        }

        $batch = InventoryBatch::create([
            'item_id' => $inventoryItem->id,
            'batch_number' => $request->input('batch_number'),
            'quantity_received' => $quantity,
            'quantity_on_hand' => $quantity,
            'expiry_date' => $expiryDate,
            'supplier' => $request->input('supplier'),
            'unit_cost' => $request->input('unit_cost'),
            'received_by' => $request->user()->id,
        ]);

        AuditLogger::log(
            $request->user(), 'receive_inventory_batch', 'inventory_item', $inventoryItem->id,
            "batch={$batch->batch_number} qty={$quantity}"
        );

        return response()->json($batch, 201);
    }

    /**
     * POST /api/inventory/consume
     * Deducts stock first-expiry-first-out across the item's batches,
     * optionally linked to the encounter/patient the consumption was for.
     */
    public function consume(Request $request)
    {
        $itemId = $request->input('item_id');
        $quantity = (int) $request->input('quantity', 0);

        if (! $itemId || $quantity <= 0) {
            return response()->json(['error' => 'missing_fields', 'message' => 'item_id and a positive quantity are required'], 400);
        }

        $item = InventoryItem::findOrFail($itemId);

        $batches = InventoryBatch::where('item_id', $itemId)
            ->where('quantity_on_hand', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->get();

        $available = $batches->sum('quantity_on_hand');
        if ($available < $quantity) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => "Only {$available} {$item->unit} of {$item->name} in stock — cannot consume {$quantity}.",
            ], 400);
        }

        $remaining = $quantity;
        $records = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($batch->quantity_on_hand, $remaining);
            $batch->decrement('quantity_on_hand', $take);
            $remaining -= $take;

            $records[] = InventoryConsumption::create([
                'item_id' => $itemId,
                'batch_id' => $batch->id,
                'quantity' => $take,
                'department' => $request->input('department'),
                'encounter_id' => $request->input('encounter_id'),
                'patient_id' => $request->input('patient_id'),
                'reason' => $request->input('reason'),
                'consumed_by' => $request->user()->id,
            ]);
        }

        AuditLogger::log(
            $request->user(), 'consume_inventory', 'inventory_item', $itemId,
            "qty={$quantity} encounter_id={$request->input('encounter_id')}"
        );

        return response()->json([
            'item' => $this->itemSummary($item->fresh()),
            'consumption_records' => $records,
        ], 201);
    }

    /**
     * GET /api/inventory/consumption?item_id=&encounter_id=&department=
     */
    public function listConsumption(Request $request)
    {
        $query = InventoryConsumption::query();

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->query('item_id'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }
        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        $records = $query->latest()->limit(200)->get();

        $result = $records->map(function (InventoryConsumption $r) {
            $d = $r->toArray();
            $item = InventoryItem::find($r->item_id);
            $d['item_name'] = $item?->name;

            if ($r->patient_id) {
                $patient = Patient::find($r->patient_id);
                $d['patient_name'] = $patient ? "{$patient->given_name} {$patient->family_name}" : null;
                $d['patient_uid'] = $patient?->patient_uid;
            }
            if ($r->encounter_id) {
                $d['mrn'] = Encounter::find($r->encounter_id)?->mrn;
            }

            return $d;
        });

        return response()->json($result);
    }

    /**
     * GET /api/inventory/alerts?horizon_days=90
     * Low-stock alerts and expiry alerts (within the horizon, or already expired).
     */
    public function alerts(Request $request)
    {
        $horizonDays = (int) $request->query('horizon_days', 90);
        $cutoff = now()->addDays($horizonDays)->toDateString();

        $items = InventoryItem::all();
        $lowStock = $items->map(fn ($i) => $this->itemSummary($i))->filter(fn ($s) => $s['low_stock'])->values();

        $expiringBatches = InventoryBatch::where('quantity_on_hand', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->orderBy('expiry_date')
            ->get();

        $today = now()->toDateString();
        $expiring = $expiringBatches->map(function (InventoryBatch $b) use ($today) {
            $d = $b->toArray();
            $item = InventoryItem::find($b->item_id);
            $d['item_name'] = $item?->name;
            $d['item_category'] = $item?->category;
            $d['is_expired'] = $b->expiry_date->toDateString() < $today;

            return $d;
        });

        return response()->json(['low_stock' => $lowStock, 'expiring_batches' => $expiring]);
    }
}
