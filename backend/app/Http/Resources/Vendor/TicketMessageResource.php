<?php

namespace App\Http\Resources\Vendor;

use App\Models\VendorAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sender_role' => $this->sender_type === VendorAdmin::class ? 'vendor' : 'support',
            'message'     => $this->message,
            'created_at'  => $this->created_at?->toIso8601String(),
            'attachments' => $this->whenLoaded('attachments', fn () =>
                $this->attachments->flatMap(fn ($a) => $a->files)->map(fn ($f) => [
                    'url'  => asset('storage/' . $f->path),
                    'name' => $f->original_name ?? null,
                ])->values()
            ),
        ];
    }
}
