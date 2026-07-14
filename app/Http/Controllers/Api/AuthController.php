<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login (and /api/auth/login alias)
     * Issues a Sanctum token for a valid, active user.
     *
     * Accepts either `email` or `username` as the identifier field, since
     * the frontend's login form posts `username` even though the value
     * typed in is an email address — this avoids touching frontend code
     * you don't own just to match a naming convention.
     */
    public function login(Request $request)
    {
        $identifier = $request->input('email') ?? $request->input('username');

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! $identifier) {
            throw ValidationException::withMessages([
                'email' => ['Email (or username) is required.'],
            ]);
        }

        $user = User::where('email', $identifier)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is not active. Contact your system administrator.'],
            ]);
        }

        $token = $user->createToken('nullcare-emr')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        // Both key names included: `access_token` for the current frontend,
        // `token` for the Postman collection / curl examples already in use.
        return response()->json([
            'access_token' => $token,
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * POST /api/logout
     * Revokes the token used for this request only (not all sessions).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/me
     * Returns the authenticated user + role, for building the role-based dashboard on load.
     */
    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    private function formatUser(User $user): array
    {
        $user->loadMissing('role', 'department');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role->name,
            'department' => $user->department?->name,
            'status' => $user->status,
        ];
    }
}