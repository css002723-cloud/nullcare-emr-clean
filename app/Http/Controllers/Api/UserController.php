<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/users
     */
    public function index()
    {
        return UserResource::collection(User::with('role', 'department')->orderBy('full_name')->get());
    }

    /**
     * POST /api/users
     * Body: { full_name, username, password, role, department, email, phone }
     * "role" and "department" arrive as plain strings from AdminUsers.jsx —
     * resolved/created against the roles/departments tables here so the
     * rest of the system (role middleware, department FK) keeps working
     * on real relations rather than loose strings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:4'],
            'role' => ['required', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $role = Role::firstOrCreate(['name' => $validated['role']]);
        $department = ! empty($validated['department'])
            ? Department::firstOrCreate(['name' => $validated['department']], ['type' => 'outpatient'])
            : null;

        $user = User::create([
            'full_name' => $validated['full_name'],
            'username' => $validated['username'],
            // Users created here without an email still need a unique value
            // for the existing email column — fall back to a synthetic one
            // derived from the username rather than leaving it null-vs-null
            // colliding under the column's unique index.
            'email' => $validated['email'] ?? $validated['username'].'@nullcare.local',
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'department_id' => $department?->id,
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
            'is_active' => true,
        ]);

        return response()->json(new UserResource($user->load('role', 'department')), 201);
    }

    /**
     * PUT /api/users/{user}
     * Body may contain only { role } or only { is_active } — AdminUsers.jsx
     * sends one field at a time depending on which action was clicked.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['role'])) {
            $role = Role::firstOrCreate(['name' => $validated['role']]);
            $user->role_id = $role->id;
        }

        if (array_key_exists('is_active', $validated)) {
            $user->is_active = $validated['is_active'];
            $user->status = $validated['is_active'] ? 'active' : 'inactive';
        }

        if (array_key_exists('department', $validated)) {
            $department = ! empty($validated['department'])
                ? Department::firstOrCreate(['name' => $validated['department']], ['type' => 'outpatient'])
                : null;
            $user->department_id = $department?->id;
        }

        $user->save();

        return new UserResource($user->fresh(['role', 'department']));
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
