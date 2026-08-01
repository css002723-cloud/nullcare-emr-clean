<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIdleTimeout
{
    private const IDLE_MINUTES = 30;

    /**
     * Sanctum automatically updates a token's `last_used_at` on every
     * authenticated request, so we don't need to build our own activity
     * tracker — we just check how long it's been since that timestamp.
     * If a token hasn't been used in 30+ minutes, it's revoked and the
     * request is rejected as unauthenticated, forcing a fresh login.
     * The frontend's own inactivity timer (mouse/keyboard) is the first
     * line of defense; this is the backend guarantee that holds even if
     * a tab is left open and the frontend timer never fires.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->last_used_at && $token->last_used_at->lt(now()->subMinutes(self::IDLE_MINUTES))) {
            $token->delete();

            return response()->json([
                'message' => 'Session expired due to inactivity. Please log in again.',
            ], 401);
        }

        return $next($request);
    }
}
