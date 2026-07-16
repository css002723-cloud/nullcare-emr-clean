<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->created_at,
            'username' => $this->whenLoaded('user', fn () => $this->user->username ?? $this->user->full_name),
            'action' => $this->action,
            'entity_type' => $this->table_name,
            'entity_id' => $this->record_id,
            'details' => $this->summarize(),
            'ip_address' => $this->ip_address,
        ];
    }

    /**
     * Builds a short human-readable summary from the raw old/new JSON,
     * since the frontend's AdminAudit screen displays a single "details"
     * string per row rather than raw diff data.
     */
    private function summarize(): string
    {
        if ($this->action === 'created' || str_starts_with((string) $this->action, 'created_')) {
            return 'Created new '.str_replace('_', ' ', (string) $this->table_name).' record #'.$this->record_id;
        }

        if ($this->action === 'deleted' || str_starts_with((string) $this->action, 'deleted_')) {
            return 'Deleted '.str_replace('_', ' ', (string) $this->table_name).' record #'.$this->record_id;
        }

        $changedFields = is_array($this->new_value) ? array_keys($this->new_value) : [];

        return $changedFields
            ? 'Updated fields: '.implode(', ', $changedFields)
            : 'Updated '.str_replace('_', ' ', (string) $this->table_name).' record #'.$this->record_id;
    }
}
