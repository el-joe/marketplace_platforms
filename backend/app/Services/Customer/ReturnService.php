<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Notifications\Vendor\ReturnRequestSubmitted;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * enhancement.md P-10: this was a duplicate of
 * Api/Customer/ReturnRequestController's own inline create logic, with
 * none of the eligibility checks and a crash (`$items->first()` on an
 * empty/mismatched id list). It now only validates ownership of the order
 * and delegates the actual create (eligibility, quantities, splitting a
 * mixed-sub-order item list into one ReturnRequest per sub-order) to the
 * single App\Services\ReturnRequestService.
 */
class ReturnService
{
    public function __construct(private readonly \App\Services\ReturnRequestService $returnRequestService) {}

    /**
     * @return Collection<int, ReturnRequest>
     */
    public function store(Customer $customer, Order $order, array $data): Collection
    {
        $returnRequests = $this->returnRequestService->create(
            customer: $customer,
            orderItemIds: $data['order_item_ids'],
            reason: $data['reason'],
            returnType: $data['return_type'],
            reasonDescription: $data['comments'] ?? null,
        );

        foreach ($returnRequests as $returnRequest) {
            $returnRequest->loadMissing('vendor.vendorAdmins');
            if ($returnRequest->vendor?->vendorAdmins->isNotEmpty()) {
                Notification::send($returnRequest->vendor->vendorAdmins, new ReturnRequestSubmitted($returnRequest));
            }
        }

        return $returnRequests;
    }

    public function listForCustomer(Customer $customer): LengthAwarePaginator
    {
        return ReturnRequest::where('customer_id', $customer->id)
            ->with(['order', 'items.orderItem'])
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function findForCustomer(Customer $customer, string $returnNumber): ?ReturnRequest
    {
        return ReturnRequest::where('return_number', $returnNumber)
            ->where('customer_id', $customer->id)
            ->with(['order', 'items.orderItem', 'refund'])
            ->first();
    }
}
