<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login (and /api/auth/login alias)
     * Body: { username, password } — or { email, password } for
     * Postman/curl testing convenience. Looks up by the real `username`
     * column first (added in the Phase 1 schema migration), falling back
     * to email only if no username match is found.
     */
    public function login(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $identifier = $request->input('username') ?? $request->input('email');

        if (! $identifier) {
            throw ValidationException::withMessages([
                'username' => ['Username (or email) is required.'],
            ]);
        }

        $user = User::where('username', $identifier)->first()
            ?? User::where('email', $identifier)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active || $user->status !== 'active') {
            throw ValidationException::withMessages([
                'username' => ['This account is not active. Contact your system administrator.'],
            ]);
        }

        $token = $user->createToken('nullcare-emr')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        // Both key names included: `access_token` for the current frontend,
        // `token` for the Postman collection / curl examples already in use.
        return response()->json([
            'access_token' => $token,
            'token' => $token,
            'user' => new UserResource($user->load('role', 'department')),
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request)
    {
        return new UserResource($request->user()->load('role', 'department'));
    }
}
