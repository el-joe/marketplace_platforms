<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\DisputeMessageSenderRole;
use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    private function admin(): VendorAdmin
    {
        return Auth::guard('vendor_api')->user();
    }

    /** GET /api/partner/v1/support-tickets */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->admin();

        $query = SupportTicket::where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)));
    }

    /** GET /api/partner/v1/support-tickets/{ticketNumber} */
    public function show(string $ticketNumber): JsonResponse
    {
        $admin = $this->admin();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->firstOrFail();

        return ApiResponse::success($ticket);
    }

    /** POST /api/partner/v1/support-tickets */
    public function store(Request $request): JsonResponse
    {
        $admin = $this->admin();

        $validated = $request->validate([
            'category' => ['required', 'in:order_issue,payment_issue,account,technical,product_inquiry,policy,payout,catalog,other'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high'],
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

        TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => VendorAdmin::class,
            'sender_id' => (string) $admin->id,
            'message' => $validated['description'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket opened successfully.',
            'ticket_number' => $ticketNumber,
        ], 201);
    }

    /** POST /api/partner/v1/support-tickets/{ticketNumber}/replies */
    public function reply(Request $request, string $ticketNumber): JsonResponse
    {
        $admin = $this->admin();

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $admin->id)
            ->where('requester_role', SupportTicketRequesterRole::Seller)
            ->firstOrFail();

        if (in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
            return response()->json(['success' => false, 'message' => 'Cannot reply to a closed or resolved ticket.'], 422);
        }

        $validated = $request->validate(['message' => ['required', 'string', 'max:10000']]);

        $msg = TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => VendorAdmin::class,
            'sender_id' => (string) $admin->id,
            'message' => $validated['message'],
        ]);

        if ($ticket->status === SupportTicketStatus::WaitingCustomer) {
            $ticket->update(['status' => SupportTicketStatus::Open]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully.',
            'data' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_role' => DisputeMessageSenderRole::Seller->value,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-'.date('ymd').'-'.strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
