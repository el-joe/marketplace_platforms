<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'ticket_number'         => $this->ticket_number,
            'category'              => $this->category,
            'priority'              => $this->priority,
            'status'                => $this->status?->value,
            'subject'               => $this->subject,
            'related_assignment_id' => $this->related_assignment_id,
            'created_at'            => $this->created_at?->toIso8601String(),
            'resolved_at'           => $this->resolved_at?->toIso8601String(),
            'satisfaction_rating'   => $this->satisfaction_rating,
            'satisfaction_comment'  => $this->satisfaction_comment,
            'messages'              => TicketMessageResource::collection(
                $this->whenLoaded('messages')
            ),
        ];
    }
}
