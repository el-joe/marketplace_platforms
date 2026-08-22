<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->user('customer')?->locale ?? 'ar';
        $data = $this->data ?? [];

        if ($locale === 'ar') {
            $data['title'] = $data['title_ar'] ?? $data['title'] ?? null;
            $data['message'] = $data['message_ar'] ?? $data['message'] ?? null;
        }

        return [
            'id'         => $this->id,
            'type'       => class_basename($this->type),
            'data'       => $data,
            'is_read'    => $this->read_at !== null,
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }
}
