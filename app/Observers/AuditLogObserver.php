<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Generic observer attached to every clinically/financially significant
 * model (see AppServiceProvider::boot()). Writes one audit_logs row per
 * create/update/delete so no controller has to remember to log manually.
 *
 * Deliberately skips logging when there's no authenticated user (e.g.
 * console seeders) — audit trail is about accountability for user actions,
 * not a general changelog.
 */
class AuditLogObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->log('updated', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, $model->getOriginal(), null);
    }

    private function log(string $action, Model $model, ?array $old, ?array $new): void
    {
        $userId = Auth::id();

        if (! $userId) {
            return;
        }

        // Never log the audit_logs table itself, and never write the
        // password field to an old/new value column.
        if ($model instanceof AuditLog) {
            return;
        }

        unset($old['password'], $new['password']);

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action.'_'.strtolower(class_basename($model)),
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
