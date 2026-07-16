<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditController extends Controller
{
    /**
     * GET /api/audit
     * Most recent 200 entries — the AdminAudit.jsx screen is a flat table
     * with no pagination UI yet, so a sane cap avoids ever shipping the
     * entire history in one response as the system grows.
     */
    public function index()
    {
        $logs = AuditLog::with('user')->latest()->limit(200)->get();

        return AuditLogResource::collection($logs);
    }
}
