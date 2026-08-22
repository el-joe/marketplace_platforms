<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DisputeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dispute_number'    => $this->dispute_number,
            'sub_order_number'  => $this->subOrder?->sub_order_number,
            'reason'            => $this->reason?->value,
            'description'       => $this->description,
            'status'            => $this->status?->value,
            'resolution'        => $this->resolution?->value,
            'resolution_notes'  => $this->resolution_notes,
            'compensation' => $this->compensation,
            'resolved_at'       => $this->resolved_at?->toIso8601String(),
            'created_at'        => $this->created_at->toIso8601String(),

            // Internal notes excluded — vendors see only customer/seller/admin public messages
            'messages' => $this->whenLoaded('messages', fn () =>
                $this->messages
                    ->where('is_internal_note', false)
                    ->values()
                    ->map(fn ($msg) => [
                        'id'          => $msg->id,
                        'sender_role' => $msg->sender_role?->value,
                        'message'     => $msg->message,
                        'created_at'  => $msg->created_at->toIso8601String(),
                    ])
            ),

            'evidence' => $this->whenLoaded('evidence', fn () =>
                $this->evidence->map(fn ($ev) => [
                    'id'          => $ev->id,
                    'file_name'   => $ev->file_name,
                    'description' => $ev->description,
                    'uploaded_by' => $ev->uploaded_by_role,
                    'url'         => Storage::temporaryUrl($ev->file_path, now()->addMinutes(30)),
                    'created_at'  => $ev->created_at->toIso8601String(),
                ])
            ),
        ];
    }
}
