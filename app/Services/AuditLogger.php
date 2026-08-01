<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * Matches log_action() in the Python reference: an explicit call at
     * each meaningful action site, rather than a generic model observer.
     * This is a deliberate style match — the reference logs specific,
     * human-readable actions ("dispensed_prescription", "closed_encounter")
     * with a one-line details string, not a raw before/after diff. Call
     * this directly from controllers, same as the Flask routes do.
     */
    public static function log(?User $user, string $action, string $entityType, int|string|null $entityId, ?string $details = null, ?string $ipAddress = null): void
    {
        AuditLog::create([
            'timestamp' => now(),
            'user_id' => $user?->id,
            'username' => $user?->username,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'details' => $details,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
