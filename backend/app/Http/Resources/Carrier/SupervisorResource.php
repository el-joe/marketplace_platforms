<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'name'                       => $this->name,
            'email'                      => $this->email,
            'phone'                      => $this->phone,
            'permissions'                => $this->permissions ?? [],
            'is_active'                  => $this->is_active,
            'receives_all_notifications' => $this->receives_all_notifications,
            'created_at'                 => $this->created_at->toIso8601String(),
            // password / remember_token are never exposed
        ];
    }
}
