<?php

namespace App\Services;

use App\Models\LoginAttempt;

class LoginAttemptRecorder
{
    /** Matches LOGIN_ATTEMPT_WINDOW_MINUTES / LOGIN_ATTEMPT_ALERT_THRESHOLD in utils.py exactly. */
    const WINDOW_MINUTES = 15;
    const ALERT_THRESHOLD = 5;

    /**
     * Logs one attempt and returns the count of recent failures for this
     * username within WINDOW_MINUTES — a successful login does NOT clear
     * prior failures, since the raw log needs to stay intact for the
     * admin-facing security-alerts view.
     */
    public static function record(string $username, bool $success): int
    {
        LoginAttempt::create([
            'username' => $username,
            'ip_address' => request()?->ip(),
            'success' => $success,
            'timestamp' => now(),
        ]);

        return LoginAttempt::where('username', $username)
            ->where('success', false)
            ->where('timestamp', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->count();
    }
}
