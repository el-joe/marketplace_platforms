<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\OrderDetailResource;
use App\Http\Resources\Api\Customer\OrderInvoiceResource;
use App\Http\Resources\Api\Customer\OrderListItemResource;
use App\Http\Resources\Api\Customer\OrderTrackingResource;
use App\Http\Resources\Api\Customer\SubOrderDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const CANCELLABLE_ORDER_STATUSES = ['placed', 'confirmed'];

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $query = Order::where('customer_id', $customer->id)
            ->select(['id', 'customer_id', 'order_number', 'status', 'payment_status', 'currency', 'total', 'placed_at'])
            ->withCount('subOrders')
            ->with(['subOrders' => fn ($q) => $q->select(['id', 'order_id', 'sub_order_number', 'shipping_method_id']),
                'subOrders.shippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex'])
            ->orderByDesc('placed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('order_number', 'like', '%'.$request->input('search').'%');
        }

        $paginator = $query->paginate(15);

        return ApiResponse::success([
            'items' => OrderListItemResource::collection(collect($paginator->items())),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], __('customer_api.order.retrieved'));
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load([
            'subOrders.items.warrantyPurchase',
            'subOrders.items.shippingMethod',
            'subOrders.vendor:id,store_name',
            'subOrders.carrier',
            'subOrders.shippingMethod',
            'statusHistories',
        ]);

        return ApiResponse::success($this->buildOrderDetail($order), __('customer_api.order.single_retrieved'));
    }

    public function showSubOrder(Request $request, string $orderNumber, string $subOrderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $subOrder = SubOrder::where('order_id', $order->id)
            ->where('sub_order_number', $subOrderNumber)
            ->with(['vendor:id,store_name', 'carrier', 'shippingMethod', 'items.shippingMethod', 'shipments.trackingEvents'])
            ->first();

        if (! $subOrder) {
            return ApiResponse::error(__('customer_api.order.sub_order_not_found'), [], 404);
        }

        return ApiResponse::success((new SubOrderDetailResource($subOrder))->toArray($request), __('customer_api.order.sub_order_retrieved'));
    }

    public function tracking(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load(['subOrders.carrier', 'subOrders.shipments.trackingEvents']);

        return ApiResponse::success((new OrderTrackingResource($order))->toArray($request), __('customer_api.order.tracking_retrieved'));
    }

    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        if (! in_array($order->status?->value, self::CANCELLABLE_ORDER_STATUSES, true)) {
            return ApiResponse::error(__('customer_api.order.cannot_cancel_status'), [], 422);
        }

        $order = DB::transaction(function () use ($order, $customer) {
            $previousStatus = $order->status?->value;

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $order->subOrders()->get()->each(function (SubOrder $subOrder) {
                $subOrder->update(['status' => 'cancelled']);

                foreach ($subOrder->items as $item) {
                    if ($item->vendor_listing_id) {
                        WarehouseInventory::where('vendor_listing_id', $item->vendor_listing_id)
                            ->lockForUpdate()
                            ->orderBy('id')
                            ->first()
                            ?->decrement('quantity_reserved', $item->quantity);
                    }
                }
            });

            $walletTxn = WalletTransaction::whereHas(
                'wallet',
                fn ($q) => $q->where('owner_type', 'customer')->where('owner_id', $customer->id)
            )
                ->where('source_type', 'order')
                ->where('source_id', $order->id)
                ->where('type', 'debit')
                ->first();

            if ($walletTxn) {
                $wallet = Wallet::where('id', $walletTxn->wallet_id)->lockForUpdate()->first();
                $wallet->increment('balance', $walletTxn->amount);
                $wallet->refresh();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $walletTxn->amount,
                    'balance_after' => $wallet->getRawOriginal('balance'),
                    'source_type' => 'order',
                    'source_id' => $order->id,
                    'description' => 'Refund for cancelled order '.$order->order_number,
                    'created_at' => now(),
                ]);
            }

            $giftCardTxn = GiftCardTransaction::where('order_id', $order->id)
                ->where('type', 'redemption')
                ->first();

            if ($giftCardTxn) {
                $giftCard = GiftCard::where('id', $giftCardTxn->gift_card_id)->lockForUpdate()->first();
                $giftCard->increment('balance', $giftCardTxn->amount);
                $giftCard->refresh();

                if ($giftCard->getRawOriginal('balance') > 0 && $giftCard->status !== 'active') {
                    $giftCard->update(['status' => 'active']);
                }

                GiftCardTransaction::create([
                    'gift_card_id' => $giftCard->id,
                    'order_id' => $order->id,
                    'amount' => $giftCardTxn->amount,
                    'balance_after' => $giftCard->getRawOriginal('balance'),
                    'type' => 'refund',
                    'performed_by_customer_id' => $customer->id,
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $previousStatus,
                'to_status' => 'cancelled',
                'reason' => 'Cancelled by customer',
            ]);

            return $order->fresh();
        });

        $order->load(['subOrders.items', 'statusHistories']);

        return ApiResponse::success($this->buildOrderDetail($order), __('customer_api.order.cancelled_successfully'));
    }

    public function invoice(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load(['subOrders.items', 'subOrders.vendor:id,store_name']);

        return ApiResponse::success((new OrderInvoiceResource($order))->toArray($request), __('customer_api.order.invoice_retrieved'));
    }

    private function findOrder(Customer $customer, string $orderNumber): ?Order
    {
        return Order::where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)
            ->first();
    }

    private function buildOrderDetail(Order $order): array
    {
        return (new OrderDetailResource($order))->toArray(request());
    }
}
