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
            'role' => $this->role,
            'department' => $this->department,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'must_reset_password' => (bool) $this->must_reset_password,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
