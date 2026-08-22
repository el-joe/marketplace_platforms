<?php

namespace App\Services\Carrier;

use App\Enums\SupportTicketStatus;
use App\Jobs\NotifyAdminNewTicketJob;
use App\Models\ShippingCompanySupervisor;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function list(ShippingCompanySupervisor $supervisor): LengthAwarePaginator
    {
        return SupportTicket::where('requester_user_id', $supervisor->id)
            ->where('requester_role', 'shipping_supervisor')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function store(ShippingCompanySupervisor $supervisor, array $data): SupportTicket
    {
        $ticket = SupportTicket::create([
            'ticket_number'         => $this->generateTicketNumber(),
            'requester_user_id'     => $supervisor->id,
            'requester_role'        => 'shipping_supervisor',
            'category'              => $data['category'],
            'priority'              => $data['priority'] ?? 'normal',
            'status'                => 'open',
            'subject'               => $data['subject'],
            'description'           => $data['message'],
            'assigned_to_admin_id'  => $data['agent_id'] ?? null,
            'related_assignment_id' => $data['assignment_id'] ?? null,
        ]);

        $message = $ticket->messages()->create([
            'sender_type'      => ShippingCompanySupervisor::class,
            'sender_id'        => $supervisor->id,
            'message'          => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $this->storeAttachment($message, $data['attachment']);
        }

        dispatch(new NotifyAdminNewTicketJob($ticket));

        return $ticket;
    }

    public function findForSupervisor(ShippingCompanySupervisor $supervisor, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $supervisor->id)
            ->where('requester_role', 'shipping_supervisor')
            ->with([
                'messages' => fn ($q) => $q->where('is_internal_note', false)
                    ->with('attachments.files')
                    ->oldest('created_at'),
            ])
            ->first();
    }

    public function addMessage(ShippingCompanySupervisor $supervisor, SupportTicket $ticket, array $data): TicketMessage
    {
        $message = $ticket->messages()->create([
            'sender_type'      => ShippingCompanySupervisor::class,
            'sender_id'        => $supervisor->id,
            'message'          => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $this->storeAttachment($message, $data['attachment']);
        }

        return $message;
    }

    public function rate(ShippingCompanySupervisor $supervisor, SupportTicket $ticket, array $data): SupportTicket
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
