<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Support\SupportTicketMessageRequest;
use App\Http\Requests\Vendor\Support\SupportTicketRateRequest;
use App\Http\Requests\Vendor\Support\SupportTicketStoreRequest;
use App\Http\Resources\Vendor\SupportTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Services\Vendor\SupportTicketService;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $ticketService) {}

    public function index(): JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $paginator   = $this->ticketService->list($vendorAdmin);

        return ApiResponse::paginated($paginator, SupportTicketResource::class);
    }

    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $ticket      = $this->ticketService->store($vendorAdmin, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), 'Ticket created.', 201);
    }

    public function show(string $ticketNumber): JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $ticket      = $this->ticketService->findForVendor($vendorAdmin, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error('Ticket not found.', [], 404);
        }

        return ApiResponse::success(new SupportTicketResource($ticket));
    }

    public function addMessage(SupportTicketMessageRequest $request, string $ticketNumber): JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $ticket      = $this->resolveTicket($vendorAdmin, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error('Ticket not found.', [], 404);
        }

        $message = $this->ticketService->addMessage($vendorAdmin, $ticket, $request->validated());

        return ApiResponse::success([
            'id'         => $message->id,
            'message'    => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
        ], 'Message sent.', 201);
    }

    public function rate(SupportTicketRateRequest $request, string $ticketNumber): JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $ticket      = $this->resolveTicket($vendorAdmin, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error('Ticket not found.', [], 404);
        }

        $ticket = $this->ticketService->rate($vendorAdmin, $ticket, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), 'Thank you for your feedback.');
    }

    private function resolveTicket($vendorAdmin, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $vendorAdmin->vendor_id)
            ->where('requester_role', 'seller')
            ->first();
    }
}
