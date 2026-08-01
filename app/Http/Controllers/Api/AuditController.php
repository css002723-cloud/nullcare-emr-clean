<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * GET /api/audit?entity_type=&username= (admin only)
     */
    public function index(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }
        if ($request->filled('username')) {
            $query->where('username', $request->query('username'));
        }

        return response()->json($query->latest('timestamp')->limit(500)->get());
    }
}
