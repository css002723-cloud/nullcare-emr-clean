<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class ReceptionQueueController extends Controller
{
    /**
     * GET /api/reception/queue
     * Today's registered patients, grouped by whether they've moved past
     * reception yet — the "queue management" view a receptionist needs to
     * see who's waiting and who's already been called through.
     */
    public function index()
    {
        $patients = Patient::whereDate('created_at', now()->toDateString())
            ->whereNull('is_duplicate_of')
            ->with(['encounters' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('created_at')
            ->get();

        $waiting = $patients->filter(fn ($p) => $p->encounters->isEmpty() || $p->encounters->first()->stage === 'triage');
        $inProgress = $patients->filter(fn ($p) => $p->encounters->isNotEmpty() && $p->encounters->first()->stage !== 'triage');

        return response()->json([
            'waiting_count' => $waiting->count(),
            'in_progress_count' => $inProgress->count(),
            'waiting' => $waiting->values()->map(fn ($p) => [
                'patient_id' => $p->id,
                'mrn' => $p->patient_number,
                'full_name' => $p->full_name,
                'registered_at' => $p->created_at,
            ]),
        ]);
    }
}
