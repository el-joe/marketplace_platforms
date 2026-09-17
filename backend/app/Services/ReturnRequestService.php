<?php

namespace App\Services;

use App\DTOs\Refund\RefundScope;
use App\Enums\InventoryMovementReferenceType;
use App\Enums\InventoryMovementType;
use App\Enums\ReturnRequestItemRestockDecision;
use App\Enums\ReturnRequestLiability;
use App\Enums\ReturnRequestStatus;
use App\Enums\ReturnRequestType;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\SubOrder;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * enhancement.md P-10: single source of truth for the return (listing
 * return) lifecycle. Both customer-facing create endpoints
 * (Customer/ReturnController -> Services/Customer/ReturnService, and
 * Api/Customer/ReturnRequestController) and the admin lifecycle actions
 * (Admin/ReturnController) delegate here.
 *
 * Eligibility enforced on create():
 *  - the order item's own sub-order/item must be delivered;
 *  - today <= the item's return_eligible_until;
 *  - the item's category must be returnable (is_returnable);
 *  - requested quantity <= purchased quantity - already returned quantity
 *    (summed across every non-rejected/non-cancelled return request);
 *  - a mixed list of items spanning several sub-orders is SPLIT into one
 *    ReturnRequest per sub-order (not rejected) — the API layer may accept
 *    a mixed order_item_ids list from the customer.
 */
class ReturnRequestService
{
    public function __construct(private readonly RefundService $refundService) {}

    /**
     * @param  string[]  $orderItemIds
     * @param  array<string, int>  $quantities  order_item_id => requested quantity (defaults to full purchased qty)
     * @return Collection<int, ReturnRequest>
     */
    public function create(
        Customer $customer,
        array $orderItemIds,
        string $reason,
        string $returnType,
        ?string $reasonDescription = null,
        array $quantities = [],
        ?string $pickupAddressId = null,
    ): Collection {
        $orderItemIds = array_values(array_unique($orderItemIds));

        return DB::transaction(function () use ($customer, $orderItemIds, $reason, $returnType, $reasonDescription, $quantities, $pickupAddressId) {
            $items = OrderItem::with(['order', 'subOrder', 'productVariant.product.category'])
                ->whereIn('id', $orderItemIds)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty() || $items->count() !== count($orderItemIds)) {
                throw ValidationException::withMessages([
                    'order_item_ids' => ['One or more order items are invalid.'],
                ]);
            }

            foreach ($items as $item) {
                $this->assertEligible($customer, $item, $quantities[$item->id] ?? (int) $item->quantity);
            }

            $created = collect();

            foreach ($items->groupBy('sub_order_id') as $subOrderId => $subOrderItems) {
                /** @var SubOrder $subOrder */
                $subOrder = $subOrderItems->first()->subOrder;
                $firstItem = $subOrderItems->first();

                $returnRequest = ReturnRequest::create([
                    'return_number' => $this->generateReturnNumber(),
                    'order_id' => $firstItem->order_id,
                    'sub_order_id' => $subOrderId,
                    'customer_id' => $customer->id,
                    'vendor_id' => $subOrder->vendor_id,
                    'reason' => $reason,
                    'reason_description' => $reasonDescription,
                    'return_type' => $returnType,
                    'status' => ReturnRequestStatus::Requested,
                    'pickup_address_id' => $pickupAddressId,
                ]);

                foreach ($subOrderItems as $item) {
                    $returnRequest->items()->create([
                        'order_item_id' => $item->id,
                        'quantity' => $quantities[$item->id] ?? (int) $item->quantity,
                    ]);
                }

                $created->push($returnRequest);
            }

            return $created;
        });
    }

    private function assertEligible(Customer $customer, OrderItem $item, int $requestedQty): void
    {
        if (! $item->order || $item->order->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'order_item_ids' => ['One or more order items do not belong to you.'],
            ]);
        }

        if ($item->fulfillment_status?->value !== 'delivered') {
            throw ValidationException::withMessages([
                'order_item_ids' => ['Only delivered items can be returned.'],
            ]);
        }

        if (! $item->return_eligible_until || $item->return_eligible_until->lt(today())) {
            throw ValidationException::withMessages([
                'order_item_ids' => ['The return window for one or more items has expired.'],
            ]);
        }

        $category = $item->productVariant?->product?->category;
        if ($category && ! $category->is_returnable) {
            throw ValidationException::withMessages([
                'order_item_ids' => ['One or more items belong to a non-returnable category.'],
            ]);
        }

        if ($requestedQty < 1) {
            throw ValidationException::withMessages([
                'order_item_ids' => ['The requested return quantity must be at least 1.'],
            ]);
        }

        $alreadyReturned = (int) ReturnRequestItem::where('order_item_id', $item->id)
            ->whereHas('returnRequest', fn ($q) => $q->whereNotIn('status', [
                ReturnRequestStatus::Rejected->value,
                ReturnRequestStatus::Cancelled->value,
            ]))
            ->sum('quantity');

        if ($requestedQty > (int) $item->quantity - $alreadyReturned) {
            throw ValidationException::withMessages([
                'order_item_ids' => ['The requested return quantity exceeds what can still be returned for one or more items.'],
            ]);
        }
    }

    public function approve(ReturnRequest $returnRequest, string $adminId): void
    {
        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            throw new \DomainException('Only requested returns can be approved.');
        }

        $returnRequest->update([
            'status' => ReturnRequestStatus::Approved->value,
            'reviewed_by_admin_id' => $adminId,
        ]);
    }

    public function reject(ReturnRequest $returnRequest, string $adminId, string $rejectionReason): void
    {
        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            throw new \DomainException('Only requested returns can be rejected.');
        }

        $returnRequest->update([
            'status' => ReturnRequestStatus::Rejected->value,
            'rejection_reason' => $rejectionReason,
            'reviewed_by_admin_id' => $adminId,
        ]);
    }

    public function schedulePickup(ReturnRequest $returnRequest, ?string $scheduledDate): void
    {
        if ($returnRequest->status !== ReturnRequestStatus::Approved) {
            throw new \DomainException('Only approved returns can have a pickup scheduled.');
        }

        $returnRequest->update([
            'status' => ReturnRequestStatus::AwaitingPickup->value,
            'pickup_scheduled_at' => $scheduledDate,
        ]);
    }

    public function markReceived(ReturnRequest $returnRequest): void
    {
        if (! in_array($returnRequest->status, [ReturnRequestStatus::AwaitingPickup, ReturnRequestStatus::InTransit], true)) {
            throw new \DomainException('Only in-transit or awaiting-pickup returns can be marked received.');
        }

        $returnRequest->update([
            'status' => ReturnRequestStatus::Received->value,
            'received_at_warehouse_at' => now(),
        ]);
    }

    public function cancel(ReturnRequest $returnRequest): void
    {
        if (! in_array($returnRequest->status, [
            ReturnRequestStatus::Requested,
            ReturnRequestStatus::Approved,
            ReturnRequestStatus::AwaitingPickup,
            ReturnRequestStatus::InTransit,
        ], true)) {
            throw new \DomainException('This return can no longer be cancelled.');
        }

        $returnRequest->update(['status' => ReturnRequestStatus::Cancelled->value]);
    }

    /**
     * @param  array<string, array{condition?: string, restock_decision?: string}>  $itemDecisions  order_item_id => decision
     */
    public function inspect(
        ReturnRequest $returnRequest,
        string $outcome,
        array $itemDecisions,
        ?string $notes,
        string $actorId,
    ): void {
        if ($returnRequest->status !== ReturnRequestStatus::Received) {
            throw new \DomainException('Only received returns can be inspected.');
        }

        DB::transaction(function () use ($returnRequest, $outcome, $itemDecisions, $notes, $actorId) {
            $returnRequest->loadMissing(['items.orderItem.subOrder']);

            $liability = match ($outcome) {
                'good' => ReturnRequestLiability::Platform,
                'damaged_by_customer' => ReturnRequestLiability::Customer,
                'damaged_in_transit' => ReturnRequestLiability::Carrier,
                default => throw new \DomainException("Unknown inspection outcome [{$outcome}]."),
            };

            foreach ($returnRequest->items as $item) {
                $decision = $itemDecisions[$item->order_item_id] ?? [];
                $item->update([
                    'condition_received' => $decision['condition'] ?? ($outcome === 'good' ? 'new' : 'damaged'),
                    'restock_decision' => $decision['restock_decision'] ?? ($outcome === 'good' ? 'restock' : 'dispose'),
                ]);
            }

            $returnRequest->update([
                'inspection_result' => $outcome === 'good' ? 'good' : 'damaged',
                'inspection_notes' => $notes,
                'liability' => $liability->value,
            ]);

            $this->applyRestockDecisions($returnRequest, $actorId);

            if ($outcome === 'damaged_by_customer') {
                // Liability rests with the customer: no refund, no restock.
                // The return stays in Inspecting for a manual admin decision
                // (a partial goodwill refund, if any) via complete().
                return;
            }

            $returnRequest->update(['status' => ReturnRequestStatus::Completed->value]);

            if ($returnRequest->return_type === ReturnRequestType::Exchange) {
                $this->createExchangeReplacement($returnRequest);

                return;
            }

            $this->processRefund($returnRequest, $actorId);
        });
    }

    /**
     * Finalizes a return that inspect() left in Inspecting (damaged by
     * customer) without a refund — an admin explicitly closes it out.
     */
    public function complete(ReturnRequest $returnRequest, string $actorId, bool $issueRefund = false): void
    {
        if ($returnRequest->status !== ReturnRequestStatus::Inspecting) {
            throw new \DomainException('Only an inspected (pending decision) return can be completed.');
        }

        DB::transaction(function () use ($returnRequest, $actorId, $issueRefund) {
            $returnRequest->update(['status' => ReturnRequestStatus::Completed->value]);

            if ($issueRefund) {
                $this->processRefund($returnRequest, $actorId);
            }
        });
    }

    private function applyRestockDecisions(ReturnRequest $returnRequest, string $actorId): void
    {
        foreach ($returnRequest->items as $item) {
            $orderItem = $item->orderItem;
            $subOrder = $orderItem?->subOrder;

            if (! $orderItem || ! $subOrder || ! $subOrder->warehouse_id) {
                continue;
            }

            $query = $orderItem->vendor_listing_id
                ? WarehouseInventory::where('vendor_listing_id', $orderItem->vendor_listing_id)
                : ($orderItem->admin_listing_id
                    ? WarehouseInventory::where('admin_listing_id', $orderItem->admin_listing_id)
                    : null);

            if (! $query) {
                continue;
            }

            // The ORIGINAL sub-order's own warehouse row — never "first
            // inventory row of the listing" (enhancement.md P-10 bug).
            $inventory = $query->where('warehouse_id', $subOrder->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                continue;
            }

            $restockDecision = $item->restock_decision?->value ?? 'restock';
            $isDamaged = $item->condition_received?->value === 'damaged';

            if ($isDamaged) {
                $inventory->increment('quantity_damaged', (int) $item->quantity);

                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => InventoryMovementType::Damage->value,
                    'quantity_delta' => 0,
                    'quantity_after' => $inventory->fresh()->quantity_on_hand,
                    'reference_type' => InventoryMovementReferenceType::ReturnRequest->value,
                    'reference_id' => $returnRequest->id,
                    'reason' => "Return request {$returnRequest->return_number} — damaged, quantity_damaged updated.",
                    'created_by_user_id' => $actorId,
                ]);

                continue;
            }

            if ($restockDecision !== ReturnRequestItemRestockDecision::Restock->value) {
                continue;
            }

            $inventory->increment('quantity_on_hand', (int) $item->quantity);
            $inventory->refresh();

            InventoryMovement::create([
                'warehouse_inventory_id' => $inventory->id,
                'movement_type' => InventoryMovementType::ReturnMovement->value,
                'quantity_delta' => (int) $item->quantity,
                'quantity_after' => $inventory->quantity_on_hand,
                'reference_type' => InventoryMovementReferenceType::ReturnRequest->value,
                'reference_id' => $returnRequest->id,
                'reason' => "Return request {$returnRequest->return_number} — good condition restock.",
                'created_by_user_id' => $actorId,
            ]);
        }
    }

    private function processRefund(ReturnRequest $returnRequest, string $actorId): void
    {
        $order = $returnRequest->order()->first();
        if (! $order) {
            return;
        }

        $returnRequest->loadMissing('items');

        $itemQuantities = $returnRequest->items
            ->mapWithKeys(fn ($item) => [$item->order_item_id => (int) $item->quantity])
            ->all();

        if (empty($itemQuantities)) {
            return;
        }

        $liability = $returnRequest->liability?->value ?? ReturnRequestLiability::Customer->value;
        $destination = $returnRequest->return_type === ReturnRequestType::StoreCredit ? 'wallet' : 'original';

        $refund = $this->refundService->refund(
            order: $order,
            scope: RefundScope::items($returnRequest->sub_order_id, $itemQuantities),
            reason: $returnRequest->reason?->value ?? 'other',
            liability: $liability,
            destination: $destination,
            initiatedBy: ['type' => 'admin', 'id' => $actorId],
            approvedByAdminId: $actorId,
            reasonNotes: "Return request {$returnRequest->return_number} inspected — refund issued.",
        );

        $returnRequest->update(['refund_id' => $refund->id, 'refund_amount' => $refund->amount]);
    }

    /**
     * enhancement.md P-10 task 3 / P-09 pattern reuse: an exchange return
     * creates a zero-price replacement sub-order + order item for each
     * returned line, the same way WarrantyClaimResolutionService::replace()
     * creates a replacement for a warranty claim.
     */
    private function createExchangeReplacement(ReturnRequest $returnRequest): void
    {
        $returnRequest->loadMissing('items.orderItem.subOrder');

        foreach ($returnRequest->items as $returnItem) {
            $orderItem = $returnItem->orderItem;
            $originalSubOrder = $orderItem?->subOrder;

            if (! $orderItem || ! $originalSubOrder) {
                continue;
            }

            $replacementSubOrder = SubOrder::create([
                'order_id' => $orderItem->order_id,
                'sub_order_number' => 'EX-'.strtoupper(Str::random(10)),
                'vendor_id' => $orderItem->vendor_id,
                'seller_type' => $originalSubOrder->seller_type ?? ($orderItem->vendor_id ? 'vendor' : 'platform'),
                'warehouse_id' => $originalSubOrder->warehouse_id,
                'status' => 'placed',
                'fulfillment_model' => $originalSubOrder->fulfillment_model,
                'subtotal' => 0,
                'shipping' => 0,
                'tax' => 0,
                'platform_commission' => 0,
                'vendor_coupon_cost' => 0,
                'platform_coupon_cost' => 0,
                'marketer_commission' => 0,
                'warranty_revenue' => 0,
                'gateway_fee' => 0,
                'vendor_payout' => 0,
            ]);

            OrderItem::create([
                'order_id' => $orderItem->order_id,
                'sub_order_id' => $replacementSubOrder->id,
                'product_variant_id' => $orderItem->product_variant_id,
                'vendor_listing_id' => $orderItem->vendor_listing_id,
                'admin_listing_id' => $orderItem->admin_listing_id,
                'marketer_listing_id' => $orderItem->marketer_listing_id,
                'product_snapshot' => $orderItem->product_snapshot,
                'vendor_id' => $orderItem->vendor_id,
                'sku' => $orderItem->sku,
                'quantity' => $returnItem->quantity,
                'unit_price' => 0,
                'unit_cost_price' => $orderItem->unit_cost_price,
                'line_subtotal' => 0,
                'line_discount' => 0,
                'line_tax' => 0,
                'line_total' => 0,
                'commission_rate_pct' => 0,
                'commission_amount' => 0,
                'fulfillment_status' => 'pending',
            ]);
        }
    }

    private function generateReturnNumber(): string
    {
        return 'RET-'.strtoupper(Str::random(10));
    }
}
