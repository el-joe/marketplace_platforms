<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Support\SupportTicketMessageRequest;
use App\Http\Requests\Customer\Support\SupportTicketRateRequest;
use App\Http\Requests\Customer\Support\SupportTicketStoreRequest;
use App\Http\Resources\Customer\SupportTicketMessageResource;
use App\Http\Resources\Customer\SupportTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Services\Customer\SupportTicketService;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $ticketService) {}

    public function index(string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $paginator = $this->ticketService->list($customer);

        return ApiResponse::paginated($paginator, SupportTicketResource::class);
    }

    public function store(SupportTicketStoreRequest $request, string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $ticket = $this->ticketService->store($customer, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('common.exceptions.support_ticket.created'), 201);
    }

    public function show(string $country, string $ticketNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $ticket = $this->ticketService->findForCustomer($customer, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('common.exceptions.support_ticket.not_found'), [], 404);
        }

        return ApiResponse::success(new SupportTicketResource($ticket));
    }

    public function addMessage(SupportTicketMessageRequest $request, string $country, string $ticketNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $ticket = $this->resolveTicket($customer, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('common.exceptions.support_ticket.not_found'), [], 404);
        }

        $message = $this->ticketService->addMessage($customer, $ticket, $request->validated());

        return ApiResponse::success(new SupportTicketMessageResource($message), __('common.exceptions.support_ticket.message_sent'), 201);
    }

    public function rate(SupportTicketRateRequest $request, string $country, string $ticketNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $ticket = $this->resolveTicket($customer, $ticketNumber);

        if (!$ticket) {
            return ApiResponse::error(__('common.exceptions.support_ticket.not_found'), [], 404);
        }

        $ticket = $this->ticketService->rate($customer, $ticket, $request->validated());

        return ApiResponse::success(new SupportTicketResource($ticket), __('common.exceptions.support_ticket.feedback_thanks'));
    }

    private function resolveTicket($customer, string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)
            ->where('requester_user_id', $customer->id)
            ->where('requester_role', 'customer')
            ->first();
    }
}
