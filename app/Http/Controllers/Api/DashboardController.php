<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugStock;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Patient;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     * NOTE: uses its own inline ["discharged","closed","deceased"] list
     * for "active encounters" — deliberately does NOT include "cancelled"
     * here, matching the reference exactly (dashboard.py doesn't import
     * the shared CLOSED_STAGES constant, it hardcodes its own). A
     * cancelled reception mistake still counts toward "active" in this
     * one metric — porting the reference as-is, not silently fixing it.
     */
    public function summary()
    {
        $today = now()->toDateString();
        $notClosed = ['discharged', 'closed', 'deceased'];

        $totalPatients = Patient::whereNull('merged_into_patient_id')->count();
        $activeEncounters = Encounter::whereNotIn('stage', $notClosed)->count();
        $todayRegistrations = Encounter::whereDate('created_at', $today)->count();
        $admitted = Encounter::where('stage', 'admitted')->count();

        $deptCounts = Encounter::whereNotIn('stage', $notClosed)
            ->selectRaw('current_department, COUNT(*) as count')
            ->groupBy('current_department')
            ->pluck('count', 'current_department');
        $departmentQueueCounts = [];
        foreach ($deptCounts as $dept => $count) {
            $departmentQueueCounts[$dept ?: 'unassigned'] = $count;
        }

        $pendingLab = LabOrder::whereIn('status', ['ordered', 'collected', 'received'])->count();
        $criticalUnack = LabResult::where('is_critical', true)->where('critical_alert_acknowledged', false)->count();

        $outstandingInvoices = Invoice::whereIn('status', ['unpaid', 'partial'])->get();
        $outstandingTotal = $outstandingInvoices->sum(fn ($i) => $i->total_amount - $i->amount_paid);

        $lowStock = DrugStock::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count();

        $visitsLast7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $visitsLast7Days[] = ['date' => $day, 'count' => Encounter::whereDate('created_at', $day)->count()];
        }

        $priorityCounts = Encounter::whereNotIn('stage', $notClosed)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');
        $priorityBreakdown = [];
        foreach ($priorityCounts as $priority => $count) {
            $priorityBreakdown[$priority ?: 'routine'] = $count;
        }

        return response()->json([
            'total_patients' => $totalPatients,
            'active_encounters' => $activeEncounters,
            'today_registrations' => $todayRegistrations,
            'admitted_patients' => $admitted,
            'department_queue_counts' => $departmentQueueCounts,
            'pending_lab_orders' => $pendingLab,
            'critical_results_unacknowledged' => $criticalUnack,
            'outstanding_billing_total' => $outstandingTotal,
            'low_stock_drug_count' => $lowStock,
            'visits_last_7_days' => $visitsLast7Days,
            'priority_breakdown' => $priorityBreakdown,
        ]);
    }
}
