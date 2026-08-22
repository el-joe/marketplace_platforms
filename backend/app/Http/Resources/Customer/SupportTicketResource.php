<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ticket_number'        => $this->ticket_number,
            'category'             => $this->category,
            'priority'             => $this->priority,
            'status'               => $this->status?->value,
            'subject'              => $this->subject,
            'created_at'           => $this->created_at?->toIso8601String(),
            'resolved_at'          => $this->resolved_at?->toIso8601String(),
            'satisfaction_rating'  => $this->satisfaction_rating,
            'satisfaction_comment' => $this->satisfaction_comment,
            'messages'             => $this->whenLoaded('messages', fn () =>
                $this->messages->map(fn ($m) => [
                    'id'         => $m->id,
                    'sender_role' => $m->sender_type === \App\Models\Customer::class ? 'customer' : 'support',
                    'message'    => $m->message,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'attachments' => $m->whenLoaded('attachments', fn () =>
                        $m->attachments->flatMap(fn ($a) => $a->files)->map(fn ($f) => [
                            'url'  => asset('storage/' . $f->path),
                            'name' => $f->original_name ?? null,
                        ])
                    ),
                ])
            ),
        ];
    }
}
