<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Ported from require_role() in utils.py: role is now a plain string
     * on the user (no relationship to load), and 'admin' always bypasses
     * the check regardless of which roles are listed — matches the
     * reference's `role != "admin"` escape hatch exactly.
     * Usage in routes: ->middleware('role:doctor,nurse')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if ($roles && $user->role !== 'admin' && ! in_array($user->role, $roles, true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'This action requires one of roles: '.implode(', ', $roles),
            ], 403);
        }

        return $next($request);
    }
}
