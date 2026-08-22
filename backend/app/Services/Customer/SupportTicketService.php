<?php

namespace App\Services\Customer;

use App\Enums\SupportTicketStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function list(Customer $customer): LengthAwarePaginator
    {
        return SupportTicket::where('requester_user_id', $customer->id)
            ->where('requester_role', 'customer')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function store(Customer $customer, array $data): SupportTicket
    {
        $relatedOrderId = null;

        if (!empty($data['order_number'])) {
            $order = Order::where('order_number', $data['order_number'])
                ->where('customer_id', $customer->id)
                ->first();
            $relatedOrderId = $order?->id;
        }

        $ticket = SupportTicket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'requester_user_id' => $customer->id,
            'requester_role' => 'customer',
            'category' => $data['category'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
            'subject' => $data['subject'],
            'description' => $data['message'],
            'related_order_id' => $relatedOrderId,
        ]);

        $message = $ticket->messages()->create([
            'sender_type' => Customer::class,
            'sender_id' => $customer->id,
            'message' => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $attachment = $message->attachments()->create([
                'ticket_message_id' => $message->id,
            ]);
            $path = $data['attachment']->store('support-tickets', 'public');
            $attachment->files()->create([
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $data['attachment']->getMimeType(),
                'size' => $data['attachment']->getSize(),
                'original_name' => $data['attachment']->getClientOriginalName(),
            ]);
        }

        dispatch(new \App\Jobs\NotifyAdminNewTicketJob($ticket));

        return $ticket;
    }

    public function findForCustomer(Customer $customer, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $customer->id)
            ->where('requester_role', 'customer')
            ->with([
                'messages' => fn ($q) => $q->where('is_internal_note', false)->with('attachments.files'),
            ])
            ->first();
    }

    public function addMessage(Customer $customer, SupportTicket $ticket, array $data): TicketMessage
    {
        $message = $ticket->messages()->create([
            'sender_type' => Customer::class,
            'sender_id' => $customer->id,
            'message' => $data['message'],
            'is_internal_note' => false,
        ]);

        if (!empty($data['attachment'])) {
            $attachment = $message->attachments()->create([
                'ticket_message_id' => $message->id,
            ]);
            $path = $data['attachment']->store('support-tickets', 'public');
            $attachment->files()->create([
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $data['attachment']->getMimeType(),
                'size' => $data['attachment']->getSize(),
                'original_name' => $data['attachment']->getClientOriginalName(),
            ]);
        }

        return $message;
    }

    public function rate(Customer $customer, SupportTicket $ticket, array $data): SupportTicket
    {
        if (!in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
            throw ValidationException::withMessages([
                'ticket' => ['Only resolved or closed tickets can be rated.'],
            ]);
        }

        $ticket->update([
            'satisfaction_rating' => $data['satisfaction_rating'],
            'satisfaction_comment' => $data['satisfaction_comment'] ?? null,
        ]);

        return $ticket;
    }

    private function generateTicketNumber(): string
    {
        return 'TKT-' . strtoupper(Str::random(8));
    }
}
