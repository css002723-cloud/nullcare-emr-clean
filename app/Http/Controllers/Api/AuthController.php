<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginAttemptRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Body: { username, password }
     * Corrected to match the reference exactly: NO account lockout. A
     * locked-out clinician mid-emergency is its own patient-safety risk —
     * every attempt is logged, and a repeated-failure pattern is surfaced
     * as a warning (not a block) both to the person logging in and on the
     * admin security-alerts view.
     */
    public function login(Request $request)
    {
        $username = strtolower(trim((string) $request->input('username')));
        $password = (string) $request->input('password');

        $user = User::where('username', $username)->first();
        $valid = $user && Hash::check($password, $user->password);

        $recentFailures = LoginAttemptRecorder::record($username, $valid);

        if (! $valid) {
            $response = ['error' => 'invalid_credentials', 'message' => 'Incorrect username or password'];

            if ($recentFailures >= LoginAttemptRecorder::ALERT_THRESHOLD) {
                $response['warning'] = 'many_failed_attempts';
                $response['message'] = "Incorrect username or password. There have been {$recentFailures} failed attempts "
                    ."for this account in the last ".LoginAttemptRecorder::WINDOW_MINUTES." minutes — if this wasn't "
                    ."you, contact your administrator.";
            }

            return response()->json($response, 401);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'account_disabled', 'message' => 'This account has been disabled by an administrator'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('nullcare-emr', [$user->role])->plainTextToken;

        AuditLogger::log($user, 'login', 'user', $user->id);

        $result = [
            'access_token' => $token,
            'user' => (new UserResource($user))->toArray($request),
        ];

        if ($user->isPasswordExpired()) {
            $result['message'] = 'Your password is more than 6 months old and must be changed before you continue.';
        }

        return response()->json($result);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * PUT /api/auth/profile
     * Body: { first_name, last_name, email, phone }
     * Lets a signed-in user edit their own contact details. Deliberately
     * does NOT touch username, role, department, or is_active — those stay
     * admin-only (see UserController::update) so a user can't quietly
     * re-scope their own access through the "edit my profile" form.
     * No new token is issued: Sanctum tokens are opaque (unlike a JWT,
     * nothing about the user is encoded in the token string itself), so
     * the existing token stays valid — the frontend just needs to refetch
     * /auth/me to pick up the new name/email, which it already does.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email,'.$request->user()->id,
            'phone' => 'nullable|string|max:30',
        ]);

        $user = $request->user();
        $user->forceFill($validated)->save();

        AuditLogger::log($user, 'update_profile', 'user', $user->id);

        return response()->json(['message' => 'Profile updated', 'user' => new UserResource($user)]);
    }

    /**
     * POST /api/change-password
     * Body: { new_password }
     */
    public function changePassword(Request $request)
    {
        $newPassword = (string) $request->input('new_password');

        if (strlen($newPassword) < 6) {
            return response()->json(['error' => 'weak_password', 'message' => 'Password must be at least 6 characters'], 400);
        }

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'must_reset_password' => false,
        ])->save();

        AuditLogger::log($user, 'change_password', 'user', $user->id);

        return response()->json(['message' => 'Password updated', 'user' => new UserResource($user)]);
    }

    /**
     * POST /api/verify-password
     * Body: { password }
     * Checks the signed-in user's password without issuing a new token —
     * this is what the idle-lock screen calls to unlock, since the person
     * is already authenticated and we just need to confirm it's still
     * them at the keyboard.
     */
    public function verifyPassword(Request $request)
    {
        $user = $request->user();
        $password = (string) $request->input('password');

        if (! Hash::check($password, $user->password)) {
            LoginAttemptRecorder::record($user->username, false);

            return response()->json(['error' => 'invalid_password', 'message' => 'Incorrect password'], 401);
        }

        LoginAttemptRecorder::record($user->username, true);

        return response()->json(['message' => 'Verified']);
    }

    /**
     * GET /api/auth/security-alerts (admin only)
     * Accounts with 5+ failed login attempts within the last 24 hours —
     * the admin-facing view of the same brute-force signal shown at login.
     */
    public function securityAlerts()
    {
        $cutoff = now()->subHours(24);

        $failures = LoginAttempt::where('success', false)->where('timestamp', '>=', $cutoff)->get();

        $grouped = $failures->groupBy(fn ($f) => $f->username ?? '(unknown)')
            ->map(function ($group, $username) {
                return [
                    'username' => $username,
                    'failed_count' => $group->count(),
                    'ip_addresses' => $group->pluck('ip_address')->filter()->unique()->sort()->values(),
                    'latest_attempt' => $group->max('timestamp')?->toIso8601String(),
                ];
            })
            ->filter(fn ($entry) => $entry['failed_count'] >= LoginAttemptRecorder::ALERT_THRESHOLD)
            ->sortByDesc('failed_count')
            ->values();

        return response()->json([
            'alerts' => $grouped,
            'window_hours' => 24,
            'threshold' => LoginAttemptRecorder::ALERT_THRESHOLD,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
