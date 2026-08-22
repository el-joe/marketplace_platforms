<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?? $data;
        }

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'channel'    => $this->channel?->value,
            'data'       => $data,
            'read'       => $this->read_at !== null,
            'read_at'    => $this->read_at,
            'sent_at'    => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
