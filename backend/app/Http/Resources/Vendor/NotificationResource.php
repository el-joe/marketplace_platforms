<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'type_short' => class_basename($this->type),
            'data'       => $data,
            'channel'    => $this->channel?->value,
            'read_at'    => $this->read_at ? Carbon::parse($this->read_at)->toIso8601String() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
        ];
    }
}
