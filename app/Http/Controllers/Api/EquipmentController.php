<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeReport;
use App\Models\EquipmentMaintenanceRecord;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EquipmentController extends Controller
{
    /**
     * GET /api/inventory/equipment?department=&status=
     */
    public function index(Request $request)
    {
        $query = Equipment::query();

        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function statuses()
    {
        return response()->json(Equipment::STATUSES);
    }

    /**
     * POST /api/inventory/equipment
     * Roles: pharmacist, lab_tech, radiologist, nurse, dialysis_tech, admin
     */
    public function store(Request $request)
    {
        if (! $request->filled('name')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'name is required'], 400);
        }

        $installDate = null;
        if ($request->filled('install_date')) {
            try {
                $installDate = Carbon::createFromFormat('Y-m-d', $request->input('install_date'))->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'invalid_date', 'message' => 'install_date must be YYYY-MM-DD'], 400);
            }
        }

        $equipment = Equipment::create([
            'name' => $request->input('name'),
            'equipment_type' => $request->input('equipment_type'),
            'department' => $request->input('department'),
            'serial_number' => $request->input('serial_number'),
            'status' => $request->input('status', 'operational'),
            'install_date' => $installDate,
            'notes' => $request->input('notes'),
        ]);

        AuditLogger::log($request->user(), 'register_equipment', 'equipment', $equipment->id, $equipment->name);

        return response()->json($equipment, 201);
    }

    /**
     * PUT /api/inventory/equipment/{equipment}
     */
    public function update(Request $request, Equipment $equipment)
    {
        if ($request->filled('status') && ! in_array($request->input('status'), Equipment::STATUSES, true)) {
            return response()->json(['error' => 'invalid_status', 'message' => 'status must be one of: '.implode(', ', Equipment::STATUSES)], 400);
        }

        foreach (['name', 'equipment_type', 'department', 'serial_number', 'status', 'notes'] as $field) {
            if ($request->has($field)) {
                $equipment->{$field} = $request->input($field);
            }
        }

        if ($request->filled('next_maintenance_due')) {
            try {
                $equipment->next_maintenance_due = Carbon::createFromFormat('Y-m-d', $request->input('next_maintenance_due'))->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'invalid_date', 'message' => 'next_maintenance_due must be YYYY-MM-DD'], 400);
            }
        }

        $equipment->save();

        AuditLogger::log($request->user(), 'update_equipment', 'equipment', $equipment->id, json_encode($request->all()));

        return response()->json($equipment);
    }

    /**
     * GET /api/inventory/equipment/{equipment}/maintenance
     */
    public function listMaintenance(Equipment $equipment)
    {
        return response()->json(EquipmentMaintenanceRecord::where('equipment_id', $equipment->id)->latest('performed_at')->get());
    }

    /**
     * POST /api/inventory/equipment/{equipment}/maintenance
     * Logging maintenance clears "under_maintenance" status back to "operational".
     */
    public function logMaintenance(Request $request, Equipment $equipment)
    {
        $record = EquipmentMaintenanceRecord::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => $request->input('maintenance_type', 'routine'),
            'performed_at' => now(),
            'performed_by_name' => $request->input('performed_by_name'),
            'notes' => $request->input('notes'),
            'cost' => $request->input('cost'),
            'logged_by' => $request->user()->id,
        ]);

        $equipment->last_maintenance_at = now();
        if ($equipment->status === 'under_maintenance') {
            $equipment->status = 'operational';
        }
        if ($request->filled('next_maintenance_due')) {
            try {
                $equipment->next_maintenance_due = Carbon::createFromFormat('Y-m-d', $request->input('next_maintenance_due'))->toDateString();
            } catch (\Exception $e) {
                // Silently ignored, matching the reference's own try/except pass behavior.
            }
        }
        $equipment->save();

        AuditLogger::log(
            $request->user(), 'log_maintenance', 'equipment', $equipment->id,
            $request->input('maintenance_type', 'routine')
        );

        return response()->json($record, 201);
    }

    /**
     * POST /api/inventory/equipment/{equipment}/downtime
     * Reporting downtime sets equipment status to "down" immediately.
     */
    public function reportDowntime(Request $request, Equipment $equipment)
    {
        $report = EquipmentDowntimeReport::create([
            'equipment_id' => $equipment->id,
            'reported_by' => $request->user()->id,
            'started_at' => now(),
            'reason' => $request->input('reason'),
            'impact_notes' => $request->input('impact_notes'),
            'status' => 'open',
        ]);

        $equipment->update(['status' => 'down']);

        AuditLogger::log($request->user(), 'report_downtime', 'equipment', $equipment->id, $request->input('reason', ''));

        return response()->json($report, 201);
    }

    /**
     * POST /api/inventory/downtime/{downtimeReport}/resolve
     */
    public function resolveDowntime(Request $request, EquipmentDowntimeReport $downtimeReport)
    {
        $downtimeReport->update(['status' => 'resolved', 'resolved_at' => now()]);

        $equipment = Equipment::find($downtimeReport->equipment_id);
        if ($equipment && $equipment->status === 'down') {
            $equipment->update(['status' => 'operational']);
        }

        AuditLogger::log($request->user(), 'resolve_downtime', 'equipment', $downtimeReport->equipment_id);

        return response()->json($downtimeReport);
    }

    /**
     * GET /api/inventory/downtime?status=
     */
    public function listDowntime(Request $request)
    {
        $query = EquipmentDowntimeReport::query();
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $reports = $query->latest('started_at')->limit(100)->get();

        $result = $reports->map(function (EquipmentDowntimeReport $r) {
            $d = $r->toArray();
            $equipment = Equipment::find($r->equipment_id);
            $d['equipment_name'] = $equipment?->name;
            $d['department'] = $equipment?->department;
            $d['downtime_hours'] = $r->started_at
                ? round(($r->resolved_at ?? now())->diffInMinutes($r->started_at) / 60, 1)
                : null;

            return $d;
        });

        return response()->json($result);
    }

    /**
     * GET /api/inventory/equipment/dashboard
     */
    public function dashboard()
    {
        $all = Equipment::all();
        $down = $all->where('status', 'down');
        $underMaintenance = $all->where('status', 'under_maintenance');

        $resolved = EquipmentDowntimeReport::where('status', 'resolved')->whereNotNull('resolved_at')->get();
        $avgDowntimeHours = $resolved->isNotEmpty()
            ? round($resolved->avg(fn ($r) => $r->resolved_at->diffInMinutes($r->started_at) / 60), 1)
            : 0;

        $upcomingCutoff = now()->addDays(30)->toDateString();
        $maintenanceDueSoon = $all->filter(fn ($e) => $e->next_maintenance_due && $e->next_maintenance_due->toDateString() <= $upcomingCutoff);

        return response()->json([
            'total_equipment' => $all->count(),
            'operational' => $all->count() - $down->count() - $underMaintenance->count(),
            'under_maintenance' => $underMaintenance->count(),
            'down' => $down->count(),
            'open_downtime_reports' => EquipmentDowntimeReport::where('status', 'open')->count(),
            'avg_resolved_downtime_hours' => $avgDowntimeHours,
            'maintenance_due_soon' => $maintenanceDueSoon->values(),
        ]);
    }
}
