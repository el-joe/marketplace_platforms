<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReturnRequestMessageResource extends JsonResource
{
    private static array $senderLabels = [
        'customer' => 'Customer',
        'seller'   => 'You',
        'admin'    => 'Admin Support',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sender_role' => $this->sender_role->value,
            'sender_label' => self::$senderLabels[$this->sender_role->value] ?? $this->sender_role->value,
            'message'     => $this->message,
            'attachments' => $this->whenLoaded('attachments', fn () =>
                $this->attachments->map(fn ($att) => [
                    'id'        => $att->id,
                    'file_type' => $att->file_type,
                    'url'       => Storage::temporaryUrl($att->file_path, now()->addMinutes(30)),
                ])
            ),
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
