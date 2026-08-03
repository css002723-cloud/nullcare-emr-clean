<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugStock;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Patient;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Which summary fields each role is allowed to see. 'admin' always
     * gets everything regardless of this map (see summary() below) —
     * this list only matters for every other role.
     */
    private const ROLE_VISIBILITY = [
        'reception' => ['total_patients', 'active_encounters', 'today_registrations', 'department_queue_counts'],
        'nurse' => [
            'total_patients', 'active_encounters', 'admitted_patients', 'today_registrations',
            'department_queue_counts', 'pending_lab_orders', 'critical_results_unacknowledged',
            'priority_breakdown', 'visits_last_7_days',
        ],
        'doctor' => [
            'total_patients', 'active_encounters', 'admitted_patients', 'today_registrations',
            'department_queue_counts', 'pending_lab_orders', 'critical_results_unacknowledged',
            'priority_breakdown', 'visits_last_7_days',
        ],
        'lab_tech' => ['pending_lab_orders', 'critical_results_unacknowledged'],
        'radiologist' => ['pending_lab_orders'],
        'pharmacist' => ['low_stock_drug_count'],
        'billing' => ['outstanding_billing_total', 'today_registrations'],
        'dialysis_tech' => ['active_encounters', 'admitted_patients'],
        'records_officer' => ['total_patients', 'today_registrations'],
    ];

    /**
     * GET /api/dashboard/summary
     * NOTE: uses its own inline ["discharged","closed","deceased"] list
     * for "active encounters" — deliberately does NOT include "cancelled"
     * here, matching the reference exactly (dashboard.py doesn't import
     * the shared CLOSED_STAGES constant, it hardcodes its own). A
     * cancelled reception mistake still counts toward "active" in this
     * one metric — porting the reference as-is, not silently fixing it.
     */
    public function summary(Request $request)
    {
        $full = $this->computeFullSummary();

        $role = $request->user()->role;
        if ($role === 'admin') {
            return response()->json($full);
        }

        $allowedKeys = self::ROLE_VISIBILITY[$role] ?? [];
        $scoped = array_intersect_key($full, array_flip($allowedKeys));

        return response()->json($scoped);
    }

    private function computeFullSummary(): array
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

        return [
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
        ];
    }
}