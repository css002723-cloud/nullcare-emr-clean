<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncQueue;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * GET /api/sync/status
     * Lightweight status the frontend's offline-sync engine can poll
     * periodically to show a "syncing.../up to date" indicator, and to
     * decide whether it needs to push queued offline records.
     */
    public function status(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'pending' => SyncQueue::where('user_id', $userId)->where('status', 'pending')->count(),
            'failed' => SyncQueue::where('user_id', $userId)->where('status', 'failed')->count(),
            'conflict' => SyncQueue::where('user_id', $userId)->where('status', 'conflict')->count(),
            'last_synced_at' => SyncQueue::where('user_id', $userId)
                ->whereNotNull('synced_at')
                ->latest('synced_at')
                ->value('synced_at'),
        ]);
    }
}