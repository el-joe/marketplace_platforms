<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\Api\Support\SupportTicketMessageRequest;
use App\Http\Requests\Delivery\Api\Support\SupportTicketRateRequest;
use App\Http\Requests\Delivery\Api\Support\SupportTicketStoreRequest;
use App\Http\Resources\Delivery\Api\SupportTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Services\Delivery\SupportTicketService;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $ticketService) {}

    public function index(): JsonResponse
    {
        $agent     = auth('delivery_api')->user();
        $paginator = $this->ticketService->list($agent);

        return ApiResponse::paginated($paginator, SupportTicketResource::class);
    }

    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $agent  = auth('delivery_api')->user();
        $ticket = $this->ticketService->store($agent, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('delivery.messages.support_tickets.created'), 201);
    }

    public function show(string $ticketNumber): JsonResponse
    {
        $agent  = auth('delivery_api')->user();
        $ticket = $this->ticketService->findForAgent($agent, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('delivery.messages.support_tickets.not_found'), [], 404);
        }

        return ApiResponse::success(new SupportTicketResource($ticket));
    }

    public function addMessage(SupportTicketMessageRequest $request, string $ticketNumber): JsonResponse
    {
        $agent  = auth('delivery_api')->user();
        $ticket = $this->resolveTicket($agent, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('delivery.messages.support_tickets.not_found'), [], 404);
        }

        $message = $this->ticketService->addMessage($agent, $ticket, $request->validated());

        return ApiResponse::success([
            'id'         => $message->id,
            'message'    => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
        ], __('delivery.messages.support_tickets.message_sent'), 201);
    }

    public function rate(SupportTicketRateRequest $request, string $ticketNumber): JsonResponse
    {
        $agent  = auth('delivery_api')->user();
        $ticket = $this->resolveTicket($agent, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('delivery.messages.support_tickets.not_found'), [], 404);
        }

        $ticket = $this->ticketService->rate($agent, $ticket, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('delivery.messages.support_tickets.thank_you_feedback'));
    }

    private function resolveTicket($agent, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $agent->id)
            ->where('requester_role', 'delivery_agent')
            ->first();
    }
}
