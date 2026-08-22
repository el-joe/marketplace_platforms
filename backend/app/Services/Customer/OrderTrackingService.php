<?php

namespace App\Services\Customer;

use App\Enums\OrderItemFulfillmentStatus;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\SubOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrderTrackingService
{
    private const ORDER_STATUS_LABELS = [
        'placed'              => ['en' => 'Order Placed', 'ar' => 'تم الطلب'],
        'confirmed'           => ['en' => 'Confirmed', 'ar' => 'مؤكد'],
        'partially_shipped'   => ['en' => 'Partially Shipped', 'ar' => 'تم الشحن جزئياً'],
        'shipped'             => ['en' => 'Shipped', 'ar' => 'تم الشحن'],
        'partially_delivered' => ['en' => 'Partially Delivered', 'ar' => 'تم التسليم جزئياً'],
        'delivered'           => ['en' => 'Delivered', 'ar' => 'تم التسليم'],
        'completed'           => ['en' => 'Completed', 'ar' => 'مكتمل'],
        'cancelled'           => ['en' => 'Cancelled', 'ar' => 'ملغى'],
        'refunded'            => ['en' => 'Refunded', 'ar' => 'مُسترد'],
        'disputed'            => ['en' => 'Disputed', 'ar' => 'متنازع عليه'],
    ];

    private const TRACKING_EVENT_LABELS = [
        'label_created'    => ['en' => 'Label Created', 'ar' => 'تم إنشاء الفاتورة'],
        'picked_up'        => ['en' => 'Picked Up', 'ar' => 'تم الاستلام'],
        'in_transit'       => ['en' => 'In Transit', 'ar' => 'في الطريق'],
        'out_for_delivery' => ['en' => 'Out for Delivery', 'ar' => 'في الطريق للتسليم'],
        'delivered'        => ['en' => 'Delivered', 'ar' => 'تم التسليم'],
        'failed'           => ['en' => 'Failed Attempt', 'ar' => 'محاولة فاشلة'],
        'returned'         => ['en' => 'Returned', 'ar' => 'مُعاد'],
    ];

    private const ASSIGNMENT_STATUS_LABELS = [
        'assigned'  => ['en' => 'Assigned to Rider', 'ar' => 'تم تعيين المندوب'],
        'accepted'  => ['en' => 'Accepted by Rider', 'ar' => 'تم القبول من المندوب'],
        'picked_up' => ['en' => 'Picked Up', 'ar' => 'تم الاستلام'],
        'delivered' => ['en' => 'Delivered', 'ar' => 'تم التسليم'],
        'failed'    => ['en' => 'Failed Attempt', 'ar' => 'محاولة فاشلة'],
    ];

    public function getOrderDetail(Order $order): array
    {
        $order->load([
            'country:id,name_en,name_ar',
            'marketerCampaign:id,tracking_url_slug',
            'subOrders' => fn ($q) => $q->with([
                'vendor:id,store_name',
                'carrier',
                'items' => fn ($q2) => $q2->with([
                    'vendorListing' => fn ($q3) => $q3->withTrashed()
                        ->with(['productVariant:id,sku,product_id']),
                ]),
                'shipments.trackingEvents',
                'deliveryAssignments.agent:id,name',
            ]),
        ]);

        $reviewedItemIds = $this->reviewedOrderItemIds($order);

        return [
            'order_number'    => $order->order_number,
            'status'          => $order->status->value,
            'status_label_en' => self::ORDER_STATUS_LABELS[$order->status->value]['en'] ?? $order->status->value,
            'status_label_ar' => self::ORDER_STATUS_LABELS[$order->status->value]['ar'] ?? $order->status->value,
            'payment_method'  => $order->payment_method,
            'payment_status'  => $order->payment_status->value,
            'placed_at'       => $order->placed_at?->toIso8601String(),
            'currency'        => $order->currency,
            'summary'         => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping' => $order->shipping,
                'cod_fee'  => $order->cod_fee,
                'tax'      => $order->tax,
                'total'    => $order->total,
            ],
            'shipping_address' => $this->buildShippingAddress($order),
            'sub_orders'        => $order->subOrders->map(
                fn (SubOrder $subOrder) => $this->buildSubOrder($subOrder, $reviewedItemIds)
            )->all(),
            'marketer_ref' => $order->marketerCampaign?->tracking_url_slug,
        ];
    }

    public function getSubOrderTracking(string $subOrderId, Customer $customer): ?array
    {
        $subOrder = SubOrder::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with([
                'vendor:id,store_name',
                'carrier',
                'shipments.trackingEvents',
                'deliveryAssignments.agent:id,name',
            ])
            ->where('sub_order_number', $subOrderId)
            ->first();

        if (!$subOrder) {
            return null;
        }

        return [
            'sub_order_number'  => $subOrder->sub_order_number,
            'status'            => $subOrder->status->value,
            'fulfillment_model' => $subOrder->fulfillment_model,
            'vendor'            => [
                'id'         => $subOrder->vendor?->id,
                'store_name' => $subOrder->vendor?->store_name,
            ],
            'tracking'       => $this->buildTracking($subOrder),
            'delivery_agent' => $this->buildDeliveryAgent($subOrder),
            'timeline'       => $this->buildTimeline($subOrder),
        ];
    }

    private function buildShippingAddress(Order $order): array
    {
        $snapshot = $order->shipping_address_snapshot ?? [];

        return [
            'recipient_name'  => $snapshot['recipient_name'] ?? null,
            'recipient_phone' => $snapshot['recipient_phone'] ?? null,
            'street_address'  => $snapshot['street_address'] ?? null,
            'area'            => $snapshot['area'] ?? null,
            'city'            => $snapshot['city']['en'] ?? null,
            'country'         => $order->country?->name_en,
        ];
    }

    private function buildSubOrder(SubOrder $subOrder, Collection $reviewedItemIds): array
    {
        return [
            "id"                => $subOrder->id,
            'sub_order_number'  => $subOrder->sub_order_number,
            'status'            => $subOrder->status->value,
            'fulfillment_model' => $subOrder->fulfillment_model,
            'vendor'            => [
                'id'         => $subOrder->vendor?->id,
                'store_name' => $subOrder->vendor?->store_name,
            ],
            'tracking'       => $this->buildTracking($subOrder),
            'delivery_agent' => $this->buildDeliveryAgent($subOrder),
            'items'          => $subOrder->items->map(
                fn (OrderItem $item) => $this->buildItem($item, $reviewedItemIds)
            )->all(),
        ];
    }

    private function buildTracking(SubOrder $subOrder): array
    {
        $events = $subOrder->shipments
            ->flatMap(fn (Shipment $shipment) => $shipment->trackingEvents)
            ->sortBy(fn (ShipmentTrackingEvent $event) => $event->occurred_at)
            ->values()
            ->map(fn (ShipmentTrackingEvent $event) => [
                'status'          => $event->status->value,
                'status_label_en' => self::TRACKING_EVENT_LABELS[$event->status->value]['en'] ?? $event->status->value,
                'status_label_ar' => self::TRACKING_EVENT_LABELS[$event->status->value]['ar'] ?? $event->status->value,
                'location'        => $event->location,
                'description'     => $event->description,
                'occurred_at'     => $event->occurred_at?->toIso8601String(),
            ])
            ->all();

        return [
            'tracking_number'         => $subOrder->tracking_number,
            'carrier'                 => $subOrder->carrier?->name,
            'estimated_delivery_date' => $subOrder->estimated_delivery_date?->toDateString(),
            'shipped_at'              => $subOrder->shipped_at?->toIso8601String(),
            'delivered_at'            => $subOrder->delivered_at?->toIso8601String(),
            'events'                  => $events,
        ];
    }

    private function buildDeliveryAgent(SubOrder $subOrder): ?array
    {
        $assignment = $subOrder->deliveryAssignments->sortByDesc('assigned_at')->first();

        if (!$assignment) {
            return null;
        }

        return [
            'name'         => $assignment->agent?->name,
            'status'       => $assignment->status->value,
            'otp_required' => $assignment->delivery_otp !== null,
            'otp_verified' => $assignment->otp_verified,
        ];
    }

    private function buildTimeline(SubOrder $subOrder): array
    {
        $shipmentEvents = $subOrder->shipments
            ->flatMap(fn (Shipment $shipment) => $shipment->trackingEvents)
            ->map(fn (ShipmentTrackingEvent $event) => [
                'source'          => 'carrier',
                'status'          => $event->status->value,
                'status_label_en' => self::TRACKING_EVENT_LABELS[$event->status->value]['en'] ?? $event->status->value,
                'status_label_ar' => self::TRACKING_EVENT_LABELS[$event->status->value]['ar'] ?? $event->status->value,
                'location'        => $event->location,
                'description'     => $event->description,
                'occurred_at'     => $event->occurred_at,
            ]);

        $assignmentEvents = $subOrder->deliveryAssignments->flatMap(
            fn (DeliveryAssignment $assignment) => collect([
                'assigned'  => $assignment->assigned_at,
                'accepted'  => $assignment->accepted_at,
                'picked_up' => $assignment->picked_up_at,
                'delivered' => $assignment->delivered_at,
                'failed'    => $assignment->failed_at,
            ])
                ->filter()
                ->map(fn (Carbon $occurredAt, string $status) => [
                    'source'          => 'delivery_agent',
                    'status'          => $status,
                    'status_label_en' => self::ASSIGNMENT_STATUS_LABELS[$status]['en'] ?? $status,
                    'status_label_ar' => self::ASSIGNMENT_STATUS_LABELS[$status]['ar'] ?? $status,
                    'location'        => null,
                    'description'     => $status === 'failed' ? $assignment->failure_notes : null,
                    'occurred_at'     => $occurredAt,
                ])
        );

        return $shipmentEvents->concat($assignmentEvents)
            ->sortBy('occurred_at')
            ->values()
            ->map(fn (array $event) => [
                ...$event,
                'occurred_at' => $event['occurred_at']?->toIso8601String(),
            ])
            ->all();
    }

    private function buildItem(OrderItem $item, Collection $reviewedItemIds): array
    {
        $snapshot = $item->product_snapshot ?? [];
        $canReturn = $item->fulfillment_status === OrderItemFulfillmentStatus::Delivered
            && $item->return_eligible_until !== null
            && $item->return_eligible_until->greaterThanOrEqualTo(today());
        $canReview = $item->fulfillment_status === OrderItemFulfillmentStatus::Delivered
            && !$reviewedItemIds->contains($item->id);

        return [
            'id'                    => $item->id,
            'sku'                   => $item->sku,
            'listing_ref'           => $item->vendorListing
                ? app(ListingIdentifierService::class)->buildListingRef($item->vendorListing)
                : null,
            'name_en'               => $snapshot['name_en'] ?? null,
            'name_ar'               => $snapshot['name_ar'] ?? null,
            'thumbnail'             => $snapshot['thumbnail_url'] ?? null,
            'quantity'              => $item->quantity,
            'unit_price'            => $item->unit_price,
            'line_total'      => $item->line_total,
            'fulfillment_status'    => $item->fulfillment_status->value,
            'return_eligible_until' => $item->return_eligible_until?->toDateString(),
            'can_return'            => $canReturn,
            'can_review'            => $canReview,
        ];
    }

    private function reviewedOrderItemIds(Order $order): Collection
    {
        $itemIds = $order->subOrders->flatMap(fn (SubOrder $subOrder) => $subOrder->items)->pluck('id');

        return Review::where('customer_id', $order->customer_id)
            ->whereIn('order_item_id', $itemIds)
            ->pluck('order_item_id');
    }
}
