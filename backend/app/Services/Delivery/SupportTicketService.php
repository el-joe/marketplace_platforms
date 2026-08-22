<?php

namespace App\Services\Delivery;

use App\Enums\SupportTicketStatus;
use App\Jobs\NotifyAdminNewTicketJob;
use App\Models\DeliveryAgent;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function list(DeliveryAgent $agent): LengthAwarePaginator
    {
        return SupportTicket::where('requester_user_id', $agent->id)
            ->where('requester_role', 'delivery_agent')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function store(DeliveryAgent $agent, array $data): SupportTicket
    {
        $ticket = SupportTicket::create([
            'ticket_number'         => $this->generateTicketNumber(),
            'requester_user_id'     => $agent->id,
            'requester_role'        => 'delivery_agent',
            'category'              => $data['category'],
            'priority'              => $data['priority'] ?? 'normal',
            'status'                => 'open',
            'subject'               => $data['subject'],
            'description'           => $data['message'],
            'related_assignment_id' => $data['assignment_id'] ?? null,
        ]);

        $message = $ticket->messages()->create([
            'sender_type'      => DeliveryAgent::class,
            'sender_id'        => $agent->id,
            'message'          => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $this->storeAttachment($message, $data['attachment']);
        }

        dispatch(new NotifyAdminNewTicketJob($ticket));

        return $ticket;
    }

    public function findForAgent(DeliveryAgent $agent, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $agent->id)
            ->where('requester_role', 'delivery_agent')
            ->with([
                'messages' => fn ($q) => $q->where('is_internal_note', false)
                    ->with('attachments.files')
                    ->oldest('created_at'),
            ])
            ->first();
    }

    public function addMessage(DeliveryAgent $agent, SupportTicket $ticket, array $data): TicketMessage
    {
        $message = $ticket->messages()->create([
            'sender_type'      => DeliveryAgent::class,
            'sender_id'        => $agent->id,
            'message'          => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $this->storeAttachment($message, $data['attachment']);
        }

        return $message;
    }

    public function rate(DeliveryAgent $agent, SupportTicket $ticket, array $data): SupportTicket
    {
        if (!in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
            throw ValidationException::withMessages([
                'ticket' => ['Only resolved or closed tickets can be rated.'],
            ]);
        }

        $ticket->update([
            'satisfaction_rating'  => $data['satisfaction_rating'],
            'satisfaction_comment' => $data['satisfaction_comment'] ?? null,
        ]);

        return $ticket->fresh();
    }

    private function storeAttachment(TicketMessage $message, $file): void
    {
        $attachment = $message->attachments()->create([
            'ticket_message_id' => $message->id,
        ]);
        $path = $file->store('support-tickets', 'public');
        $attachment->files()->create([
            'path'          => $path,
            'disk'          => 'public',
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    private function generateTicketNumber(): string
    {
        return 'TKT-' . strtoupper(Str::random(8));
    }
}
