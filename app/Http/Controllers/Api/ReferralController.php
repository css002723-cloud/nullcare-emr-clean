<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /** Matches ROLE_TO_DEPARTMENTS in utils.py exactly — null means "sees every department." */
    private const ROLE_TO_DEPARTMENTS = [
        'reception' => ['reception'],
        'nurse' => ['triage', 'ward'],
        'doctor' => ['consultation'],
        'lab_tech' => ['laboratory'],
        'radiologist' => ['imaging'],
        'pharmacist' => ['pharmacy'],
        'billing' => ['billing'],
        'dialysis_tech' => ['dialysis'],
        'records_officer' => [],
        'admin' => null,
    ];

    /** Any clinical role can send a patient onward with a message. */
    private const MESSAGE_SENDER_ROLES = [
        'doctor', 'nurse', 'lab_tech', 'radiologist', 'pharmacist', 'dialysis_tech', 'billing', 'admin',
    ];

    private function enrich(Referral $referral): array
    {
        $d = $referral->toArray();
        $patient = Patient::find($referral->patient_id);
        $d['patient'] = $patient?->toArray();
        $sender = User::find($referral->referred_by);
        $d['referred_by_name'] = $sender?->full_name;
        $d['referred_by_role'] = $sender?->role;
        $encounter = Encounter::find($referral->encounter_id);
        $d['encounter_number'] = $encounter?->encounter_number;

        return $d;
    }

    /**
     * GET /api/referrals?to_department=&status=
     * Departmental inbox: what's been referred TO a specific department.
     */
    public function index(Request $request)
    {
        $query = Referral::query();

        if ($request->filled('to_department')) {
            $query->where('to_department', $request->query('to_department'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $referrals = $query->latest()->get();

        return response()->json($referrals->map(fn ($r) => $this->enrich($r)));
    }

    /**
     * GET /api/referrals/inbox?department=
     * Auto-resolved to the signed-in user's own department(s) from their
     * role — a radiologist automatically sees the "imaging" inbox. Admins
     * can pass ?department=X to inspect any specific one, or see everything.
     */
    public function inbox(Request $request)
    {
        $role = $request->user()->role;
        $override = $request->query('department');

        $departments = self::ROLE_TO_DEPARTMENTS[$role] ?? [];

        $query = Referral::query();

        if ($override) {
            $query->where('to_department', $override);
        } elseif ($departments !== null) {
            if (empty($departments)) {
                return response()->json(['messages' => [], 'unread_count' => 0]);
            }
            $query->whereIn('to_department', $departments);
        }

        $referrals = $query->latest()->limit(100)->get();
        $unreadCount = $referrals->where('is_read', false)->count();

        return response()->json([
            'messages' => $referrals->map(fn ($r) => $this->enrich($r)),
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/referrals
     * Roles: doctor, nurse, lab_tech, radiologist, pharmacist, dialysis_tech, billing, admin
     * The same action whether it's "refer" or "send a message" — moves the
     * encounter into the receiving department's queue and stamps stage=referred.
     */
    public function store(Request $request)
    {
        if (! in_array($request->user()->role, self::MESSAGE_SENDER_ROLES, true)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if (! $request->filled('encounter_id') || ! $request->filled('to_department')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id and to_department are required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));

        $referral = Referral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'from_department' => $encounter->current_department,
            'to_department' => $request->input('to_department'),
            'reason' => $request->input('reason'),
            'priority' => $request->input('priority', 'routine'),
            'referred_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        $encounter->update([
            'current_department' => $request->input('to_department'),
            'stage' => 'referred',
        ]);

        AuditLogger::log($request->user(), 'create_referral', 'encounter', $encounter->id, "to={$request->input('to_department')}");

        return response()->json($this->enrich($referral), 201);
    }

    /**
     * POST /api/referrals/{referral}/read
     */
    public function markRead(Referral $referral)
    {
        $referral->update(['is_read' => true]);

        return response()->json($referral);
    }

    /**
     * POST /api/referrals/{referral}/accept
     */
    public function accept(Request $request, Referral $referral)
    {
        $referral->update([
            'status' => 'accepted',
            'accepted_by' => $request->user()->id,
            'is_read' => true,
        ]);

        Encounter::where('id', $referral->encounter_id)->update(['stage' => 'in_consultation']);

        AuditLogger::log($request->user(), 'accept_referral', 'referral', $referral->id);

        return response()->json($this->enrich($referral));
    }

    /**
     * POST /api/referrals/{referral}/decline
     */
    public function decline(Request $request, Referral $referral)
    {
        $referral->update(['status' => 'declined', 'is_read' => true]);

        AuditLogger::log($request->user(), 'decline_referral', 'referral', $referral->id, $request->input('reason', ''));

        return response()->json($this->enrich($referral));
    }
}
