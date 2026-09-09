<?php

namespace App\Http\Controllers\Marketer;

use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $marketer = $this->marketer();

        $tickets = SupportTicket::where('requester_user_id', $marketer->id)
            ->where('requester_role', SupportTicketRequesterRole::Marketer)
            ->orderByDesc('created_at')
            ->get();

        return view('marketer.support.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('marketer.support.create');
    }

    public function show(string $ticketNumber): View
    {
        $marketer = $this->marketer();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $marketer->id)
            ->where('requester_role', SupportTicketRequesterRole::Marketer)
            ->firstOrFail();

        $messages = $ticket->messages()
            ->where('is_internal_note', false)
            ->oldest('created_at')
            ->get();

        return view('marketer.support.show', compact('ticket', 'messages'));
    }

    public function store(Request $request): JsonResponse
    {
        $marketer = $this->marketer();

        $validated = $request->validate([
            'category' => 'required|in:order_issue,payment_issue,account,technical,product_inquiry,policy,payout,catalog,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,high',
        ]);

        $ticketNumber = $this->generateTicketNumber();

        $ticket = SupportTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => $ticketNumber,
            'requester_user_id' => (string) $marketer->id,
            'requester_role' => SupportTicketRequesterRole::Marketer,
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'normal',
            'status' => SupportTicketStatus::Open,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
        ]);

        TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => Marketer::class,
            'sender_id' => (string) $marketer->id,
            'message' => $validated['description'],
        ]);

        return response()->json([
            'message' => 'تم فتح التذكرة بنجاح',
            'ticket_number' => $ticketNumber,
            'redirect' => route('marketer.support.show', $ticketNumber),
        ], 201);
    }

    public function reply(Request $request, string $ticketNumber): JsonResponse
    {
        $marketer = $this->marketer();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $marketer->id)
            ->where('requester_role', SupportTicketRequesterRole::Marketer)
            ->firstOrFail();

        if (in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
            return response()->json(['message' => 'لا يمكن الرد على تذكرة مغلقة أو محلولة'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $msg = TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => Marketer::class,
            'sender_id' => (string) $marketer->id,
            'message' => $validated['message'],
        ]);

        if ($ticket->status === SupportTicketStatus::WaitingCustomer) {
            $ticket->update(['status' => SupportTicketStatus::Open]);
        }

        return response()->json([
            'message' => 'تم إرسال ردك بنجاح',
            'msg' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function close(string $ticketNumber): JsonResponse
    {
        $marketer = $this->marketer();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $marketer->id)
            ->where('requester_role', SupportTicketRequesterRole::Marketer)
            ->firstOrFail();

        $ticket->update(['status' => SupportTicketStatus::Closed]);

        return response()->json(['message' => 'تم إغلاق التذكرة']);
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . date('ymd') . '-' . strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
