<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrier\Support\SupportTicketMessageRequest;
use App\Http\Requests\Carrier\Support\SupportTicketRateRequest;
use App\Http\Requests\Carrier\Support\SupportTicketStoreRequest;
use App\Http\Resources\Carrier\SupportTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Services\Carrier\SupportTicketService;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $ticketService) {}

    public function index(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $paginator  = $this->ticketService->list($supervisor);

        return ApiResponse::paginated($paginator, SupportTicketResource::class);
    }

    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $ticket     = $this->ticketService->store($supervisor, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('carrier.api.ticket_created'), 201);
    }

    public function show(string $ticketNumber): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $ticket     = $this->ticketService->findForSupervisor($supervisor, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('carrier.api.ticket_not_found'), [], 404);
        }

        return ApiResponse::success(new SupportTicketResource($ticket));
    }

    public function addMessage(SupportTicketMessageRequest $request, string $ticketNumber): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $ticket     = $this->resolveTicket($supervisor, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('carrier.api.ticket_not_found'), [], 404);
        }

        $message = $this->ticketService->addMessage($supervisor, $ticket, $request->validated());

        return ApiResponse::success([
            'id'         => $message->id,
            'message'    => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
        ], __('carrier.api.message_sent'), 201);
    }

    public function rate(SupportTicketRateRequest $request, string $ticketNumber): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $ticket     = $this->resolveTicket($supervisor, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('carrier.api.ticket_not_found'), [], 404);
        }

        $ticket = $this->ticketService->rate($supervisor, $ticket, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('carrier.api.feedback_thanks'));
    }

    private function resolveTicket($supervisor, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $supervisor->id)
            ->where('requester_role', 'shipping_supervisor')
            ->first();
    }
}
