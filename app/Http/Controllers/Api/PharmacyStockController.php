<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePharmacyStockRequest;
use App\Models\PharmacyStock;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class PharmacyStockController extends Controller
{
    /**
     * GET /api/pharmacy-stock?low_stock=1
     */
    public function index(Request $request)
    {
        $query = PharmacyStock::query();

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity_available', '<=', 'reorder_threshold');
        }

        return response()->json($query->orderBy('drug_name')->paginate(30));
    }

    /**
     * POST /api/pharmacy-stock
     * New stock intake (new drug line or new batch).
     */
    public function store(StorePharmacyStockRequest $request)
    {
        $stock = PharmacyStock::create($request->validated());

        return response()->json($stock, 201);
    }

    /**
     * PATCH /api/pharmacy-stock/{pharmacyStock}/adjust
     * Adjusts quantity (e.g. after a dispense, stock take, or delivery).
     * Raises a low_stock system_alert the moment it crosses the threshold.
     */
    public function adjust(Request $request, PharmacyStock $pharmacyStock)
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer'], // positive to add stock, negative to deduct
        ]);

        $pharmacyStock->quantity_available = max(0, $pharmacyStock->quantity_available + $validated['delta']);
        $pharmacyStock->save();

        if ($pharmacyStock->isLowStock()) {
            SystemAlert::firstOrCreate(
                [
                    'type' => 'low_stock',
                    'message' => "Low stock: {$pharmacyStock->drug_name} ({$pharmacyStock->quantity_available} remaining)",
                    'is_resolved' => false,
                ],
                ['severity' => 'warning']
            );
        }

        return response()->json($pharmacyStock);
    }
}
