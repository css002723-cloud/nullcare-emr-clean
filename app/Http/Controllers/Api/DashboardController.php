<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\PharmacyStock;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     * Shape matched exactly to frontend/src/pages/Dashboard.jsx — every
     * key here is read directly by that component, so don't rename
     * anything here without updating that file too.
     */
    public function summary()
    {
        return response()->json([
            'total_patients' => Patient::whereNull('is_duplicate_of')->count(),
            'active_encounters' => Encounter::where('status', 'open')->count(),
            'admitted_patients' => Encounter::where('status', 'admitted')->count(),
            'today_registrations' => Patient::whereDate('created_at', Carbon::today())->count(),

            'pending_lab_orders' => LabOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'critical_results_unacknowledged' => LabResult::where('is_critical', true)
                ->whereNull('verified_by')
                ->count(),
            'outstanding_billing_total' => $this->outstandingBillingTotal(),
            'low_stock_drug_count' => PharmacyStock::whereColumn('quantity_available', '<=', 'reorder_threshold')->count(),

            'visits_last_7_days' => $this->visitsLast7Days(),
            'department_queue_counts' => $this->departmentQueueCounts(),
            'priority_breakdown' => $this->priorityBreakdown(),
        ]);
    }

    /**
     * Sum of (total_amount - amount already paid) across every invoice
     * that isn't fully settled yet.
     */
    private function outstandingBillingTotal(): float
    {
        return Invoice::whereIn('status', ['unpaid', 'partially_paid'])
            ->withSum('payments', 'amount_paid')
            ->get()
            ->sum(fn ($invoice) => $invoice->total_amount - ($invoice->payments_sum_amount_paid ?? 0));
    }

    /**
     * [{ date: "2026-07-06", count: 12 }, ...] for the last 7 calendar days,
     * including days with zero visits so the chart doesn't have gaps.
     */
    private function visitsLast7Days(): array
    {
        $counts = Encounter::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy('date')
            ->pluck('count', 'date');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }

    /**
     * { "OPD": 4, "Ward 3": 2, ... } — open encounters grouped by department name.
     */
    private function departmentQueueCounts(): array
    {
        return Encounter::where('status', 'open')
            ->join('departments', 'departments.id', '=', 'encounters.department_id')
            ->selectRaw('departments.name as name, COUNT(*) as count')
            ->groupBy('departments.name')
            ->pluck('count', 'name')
            ->toArray();
    }

    /**
     * { "emergency": 1, "urgent": 3, "routine": 8 } — open encounters by triage category.
     */
    private function priorityBreakdown(): array
    {
        return Encounter::where('status', 'open')
            ->whereNotNull('triage_category')
            ->selectRaw('triage_category, COUNT(*) as count')
            ->groupBy('triage_category')
            ->pluck('count', 'triage_category')
            ->toArray();
    }
}