<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private const EMAIL_PATTERN = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    /**
     * GET /api/users (admin only)
     */
    public function index()
    {
        return UserResource::collection(User::orderBy('first_name')->get());
    }

    /**
     * POST /api/users (admin only)
     * Body: { first_name, last_name, username, email, password, role, department, phone }
     */
    public function store(Request $request)
    {
        $required = ['first_name', 'last_name', 'username', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (! $request->filled($field)) {
                return response()->json(['error' => 'missing_fields', 'message' => "Missing: {$field}"], 400);
            }
        }

        if (! preg_match(self::EMAIL_PATTERN, trim($request->input('email')))) {
            return response()->json(['error' => 'invalid_email', 'message' => 'Enter a valid email address'], 400);
        }

        if (! in_array($request->input('role'), User::ROLES, true)) {
            return response()->json(['error' => 'invalid_role', 'message' => 'Unknown role'], 400);
        }

        if (User::where('username', strtolower(trim($request->input('username'))))->exists()) {
            return response()->json(['error' => 'username_taken', 'message' => 'That username is already in use'], 409);
        }

        $user = User::create([
            'first_name' => trim($request->input('first_name')),
            'last_name' => trim($request->input('last_name')),
            'username' => strtolower(trim($request->input('username'))),
            'email' => strtolower(trim($request->input('email'))),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'department' => $request->input('department'),
            'phone' => $request->input('phone'),
            'must_reset_password' => true,
            'password_changed_at' => now(),
            'is_active' => true,
        ]);

        AuditLogger::log($request->user(), 'create_user', 'user', $user->id, $user->username);

        return response()->json(new UserResource($user), 201);
    }

    /**
     * PUT /api/users/{user} (admin only)
     */
    public function update(Request $request, User $user)
    {
        if ($request->filled('email')) {
            if (! preg_match(self::EMAIL_PATTERN, trim($request->input('email')))) {
                return response()->json(['error' => 'invalid_email', 'message' => 'Enter a valid email address'], 400);
            }
            $user->email = strtolower(trim($request->input('email')));
        }

        foreach (['first_name', 'last_name', 'department', 'phone'] as $field) {
            if ($request->has($field)) {
                $user->{$field} = $request->input($field);
            }
        }

        if ($request->has('is_active')) {
            $user->is_active = (bool) $request->input('is_active');
        }

        if ($request->has('role') && in_array($request->input('role'), User::ROLES, true)) {
            $user->role = $request->input('role');
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
            $user->password_changed_at = now();
        }

        $user->save();

        AuditLogger::log($request->user(), 'update_user', 'user', $user->id);

        return new UserResource($user);
    }

    /**
     * DELETE /api/users/{user} (admin only)
     */
    public function destroy(Request $request, User $user)
    {
        AuditLogger::log($request->user(), 'delete_user', 'user', $user->id, $user->username);

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
