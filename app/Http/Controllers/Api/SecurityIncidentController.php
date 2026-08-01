<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityIncidentController extends Controller
{
    /**
     * GET /api/security/incidents
     * Admin-facing view of security_incident-type system alerts (account
     * lockouts, and anywhere else the system flags suspicious activity).
     * This is the concrete backend piece behind "how does the admin report
     * intrusions" — a reviewable, exportable incident log an admin can
     * act on or hand to whoever your institution's designated contact is
     * for external reporting obligations.
     */
    public function index()
    {
        $incidents = SystemAlert::where('type', 'security_incident')->latest()->get();

        return response()->json($incidents);
    }

    /**
     * PATCH /api/security/incidents/{systemAlert}/resolve
     */
    public function resolve(SystemAlert $systemAlert)
    {
        $systemAlert->update(['is_resolved' => true]);

        return response()->json($systemAlert);
    }

    /**
     * GET /api/security/incidents/export
     * CSV of the incident log, e.g. for handing to an institutional
     * security officer or regulator as part of an external report.
     */
    public function export()
    {
        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'message', 'severity', 'is_resolved', 'occurred_at']);

            SystemAlert::where('type', 'security_incident')->orderBy('created_at')->chunk(200, function ($alerts) use ($handle) {
                foreach ($alerts as $alert) {
                    fputcsv($handle, [$alert->id, $alert->message, $alert->severity, $alert->is_resolved ? 'yes' : 'no', $alert->created_at]);
                }
            });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="security-incidents-'.now()->format('Ymd-His').'.csv"',
        ]);
    }
}
