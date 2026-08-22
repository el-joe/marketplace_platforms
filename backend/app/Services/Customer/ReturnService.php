<?php

namespace App\Services\Customer;

use App\Enums\ReturnRequestStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Notifications\Vendor\ReturnRequestSubmitted;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReturnService
{
    public function store(Customer $customer, Order $order, array $data): ReturnRequest
    {
        $itemIds = $data['order_item_ids'];

        $items = OrderItem::whereIn('id', $itemIds)
            ->where('order_id', $order->id)
            ->get();

        $subOrder = SubOrder::find($items->first()->sub_order_id);

        $returnRequest = ReturnRequest::create([
            'return_number' => $this->generateReturnNumber(),
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'customer_id' => $customer->id,
            'vendor_id' => $subOrder->vendor_id,
            'reason' => $data['reason'],
            'reason_description' => $data['comments'] ?? null,
            'return_type' => $data['return_type'],
            'status' => ReturnRequestStatus::Requested,
        ]);

        foreach ($items as $item) {
            $returnRequest->items()->create([
                'order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ]);
        }

        Notification::send($subOrder->vendor->vendorAdmins, new ReturnRequestSubmitted($returnRequest));

        return $returnRequest;
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

    private function generateReturnNumber(): string
    {
        return 'RET-' . strtoupper(Str::random(10));
    }
}
