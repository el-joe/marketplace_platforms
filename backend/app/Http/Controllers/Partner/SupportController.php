<?php

namespace App\Http\Controllers\Partner;

use App\Enums\DisputeMessageSenderRole;
use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Support index — shows tickets (tab 1) and disputes (tab 2).
     */
    public function index(): View
    {
        $admin = $this->vendorAdmin();

        $tickets = SupportTicket::where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
            ->orderByDesc('created_at')
            ->get();

        $disputes = Dispute::where('vendor_id', $admin->vendor_id)
            ->orderByDesc('created_at')
            ->get();

        return view('partner.support.index', compact('tickets', 'disputes'));
    }

    /**
     * New ticket form.
     */
    public function create(): View
    {
        return view('partner.support.create');
    }

    /**
     * Show a single ticket conversation.
     */
    public function show(string $ticketNumber): View
    {
        $admin = $this->vendorAdmin();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
            ->firstOrFail();

        // Exclude internal notes from vendor view
        $messages = $ticket->messages()
            ->where('is_internal_note', false)
            ->oldest('created_at')
            ->get();

        return view('partner.support.show', compact('ticket', 'messages'));
    }

    /**
     * Create a new support ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $this->vendorAdmin();

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
            'requester_user_id' => (string) $admin->id,
            'requester_role' => SupportTicketRequesterRole::Seller,
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'normal',
            'status' => SupportTicketStatus::Open,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
        ]);

        // Create the opening message from the description
        TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => VendorAdmin::class,
            'sender_id' => (string) $admin->id,
            'message' => $validated['description'],
        ]);

        return response()->json([
            'message' => 'تم فتح التذكرة بنجاح',
            'ticket_number' => $ticketNumber,
            'redirect' => route('partner.support.tickets.show', $ticketNumber),
        ], 201);
    }

    /**
     * Add a reply to an existing ticket.
     */
    public function reply(Request $request, string $ticketNumber): JsonResponse
    {
        $admin = $this->vendorAdmin();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
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
            'sender_type' => VendorAdmin::class,
            'sender_id' => (string) $admin->id,
            'message' => $validated['message'],
        ]);

        // Reopen if it was waiting on customer response
        if ($ticket->status === SupportTicketStatus::WaitingCustomer) {
            $ticket->update(['status' => SupportTicketStatus::Open]);
        }

        return response()->json([
            'message' => 'تم إرسال ردك بنجاح',
            'msg' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_role' => DisputeMessageSenderRole::Seller->value,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . date('ymd') . '-' . strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
