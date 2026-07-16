<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->whenLoaded('role', fn () => $this->role->name, $this->role_id),
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
