<?php

namespace App\Http\Controllers\Partner;

use App\Enums\InventoryMovementReferenceType;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Models\ShippingCompanySupervisor;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Notifications\Carrier\NewUnassignedShipmentArrived;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class OrderController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    /**
     * Scope a SubOrder query to the authenticated vendor.
     */
    private function vendorSubOrder(string $subOrderNumber): SubOrder
    {
        return SubOrder::where('vendor_id', $this->vendorId())
            ->where('sub_order_number', $subOrderNumber)
            ->firstOrFail();
    }

    /**
     * Mask customer name: first name + last initial (e.g. "Ahmed M.")
     */
    private function maskCustomerName(?string $name): string
    {
        if (!$name)
            return '—';
        $parts = explode(' ', trim($name));
        if (count($parts) === 1)
            return $parts[0];
        $last = mb_substr(end($parts), 0, 1) . '.';
        return $parts[0] . ' ' . $last;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportOrders($request);
        }

        $statuses = ['placed', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed', 'cancelled'];

        // Status counts for filter tabs
        $counts = SubOrder::where('vendor_id', $this->vendorId())
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $slaUrgentCount = SubOrder::where('vendor_id', $this->vendorId())
            ->where('sla_ship_deadline', '<=', now()->addHours(2))
            ->whereNotIn('status', ['shipped', 'delivered', 'completed', 'cancelled'])
            ->count();

        return view('partner.orders.index', compact('statuses', 'counts', 'slaUrgentCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildOrdersQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendorId = $this->vendorId();

        $query = SubOrder::where('sub_orders.vendor_id', $vendorId)
            ->leftJoin('orders as o', 'o.id', '=', 'sub_orders.order_id')
            ->select([
                'sub_orders.id',
                'sub_orders.sub_order_number',
                'sub_orders.status',
                'sub_orders.vendor_payout',
                'sub_orders.sla_ship_deadline',
                'sub_orders.sla_breached',
                'sub_orders.created_at',
                'o.shipping_address_snapshot',
                'o.customer_id',
            ])
            ->addSelect([
                'item_count' => \App\Models\OrderItem::selectRaw('COUNT(*)')
                    ->whereColumn('sub_order_id', 'sub_orders.id'),
            ]);

        // Filters
        if ($request->filled('search.value')) {
            $query->where('sub_orders.sub_order_number', 'like', '%' . $request->input('search.value') . '%');
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            if ($request->input('status') === 'sla_urgent') {
                $query->where('sub_orders.sla_ship_deadline', '<=', now()->addHours(2))
                    ->whereNotIn('sub_orders.status', ['shipped', 'delivered', 'completed', 'cancelled']);
            } else {
                $query->where('sub_orders.status', $request->input('status'));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sub_orders.created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sub_orders.created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function exportOrders(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $orders = $this->buildOrdersQuery($request)->orderByDesc('sub_orders.created_at')->get();

        $headers = ['Sub-order Number', 'Status', 'Payout', 'Items', 'SLA Ship Deadline', 'Placed At'];

        $rows = $orders->map(fn($row) => [
            $row->sub_order_number,
            $row->status->value,
            number_format($row->vendor_payout, 2),
            $row->item_count,
            optional($row->sla_ship_deadline)->format('Y-m-d H:i'),
            $row->created_at->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('orders', $headers, $rows),
            'csv' => $this->exportCsv('orders', $headers, $rows),
            'word' => $this->exportWord('orders', 'Orders', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->buildOrdersQuery($request);

        $columns = [
            ['searchable_columns' => ['sub_orders.sub_order_number']],
            ['orderable_column' => 'sub_orders.status'],
            ['orderable_column' => 'sub_orders.vendor_payout'],
            [],
            [],
            ['orderable_column' => 'sub_orders.sla_ship_deadline'],
            ['orderable_column' => 'sub_orders.created_at'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $address = $row->shipping_address_snapshot ?? [];
            $city = $address['city'] ?? '—';
            $area = $address['area'] ?? '';
            $maskedLocation = $area ? "{$city}, {$area}" : $city;

            $slaHtml = '—';
            if ($row->sla_ship_deadline && !in_array($row->status->value, ['shipped', 'delivered', 'completed', 'cancelled'])) {
                $isUrgent = now()->addHours(2)->gt($row->sla_ship_deadline);
                $isPast = now()->gt($row->sla_ship_deadline);
                $diff = $row->sla_ship_deadline->diffForHumans();
                $class = $isPast ? 'text-red-600 font-bold' : ($isUrgent ? 'text-orange-500 font-semibold' : 'text-gray-600');
                $slaHtml = "<span class=\"{$class} text-xs\">{$diff}</span>";
            }

            return [
                'sub_order_number' => $row->sub_order_number,
                'show_url' => Route::has('partner.orders.show')
                    ? route('partner.orders.show', $row->sub_order_number)
                    : '#',
                'status' => $row->status->value,
                'vendor_payout' => number_format($row->vendor_payout, 2),
                'location' => $maskedLocation,
                'item_count' => $row->item_count,
                'sla_countdown' => $slaHtml,
                'placed_at' => $row->created_at->format('Y-m-d H:i'),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $subOrderNumber): View
    {
        $subOrder = SubOrder::where('vendor_id', $this->vendorId())
            ->where('sub_order_number', $subOrderNumber)
            ->with([
                'order',
                'order.customer',
                'items',
                'statusHistories' => fn($q) => $q->orderBy('created_at'),
                'shipments.trackingEvents',
                'carrier',
                'shippingMethod',
            ])
            ->firstOrFail();

        $carriers = ShippingCarrier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $vendorAdmin = Auth::guard('vendor')->user();
        $currency = $vendorAdmin->vendor?->country?->currency_code ?? '';

        return view('partner.orders.show', compact('subOrder', 'carriers', 'currency'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirm
    // ─────────────────────────────────────────────────────────────────────────

    public function confirm(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if ($subOrder->status !== \App\Enums\SubOrderStatus::Placed) {
            return response()->json(['success' => false, 'message' => 'الطلب لا يمكن تأكيده في حالته الحالية.'], 422);
        }

        $subOrder->update(['status' => 'confirmed']);

        OrderStatusHistory::create([
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'from_status' => 'placed',
            'to_status' => 'confirmed',
            'changed_by_admin_id' => null,
            'metadata' => json_encode([
                'vendor_id' => $this->vendorId(),
                'vendor_admin' => Auth::guard('vendor')->user()->id,
                'action' => 'confirmed',
            ]),
        ]);

        return response()->json(['success' => true, 'message' => 'تم تأكيد الطلب بنجاح.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ship
    // ─────────────────────────────────────────────────────────────────────────

    public function ship(Request $request, string $subOrderNumber): JsonResponse
    {
        $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $subOrder = $this->vendorSubOrder($subOrderNumber);
        $subOrder->loadMissing(['shippingMethod', 'warehouse.country']);

        if (!in_array($subOrder->status->value, ['placed', 'confirmed', 'processing', 'packed'])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن شحن هذا الطلب في حالته الحالية.'], 422);
        }

        if (is_null($subOrder->shipping_method_id)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تعيين طريقة شحن من قبل الإدارة. يرجى التواصل مع الدعم.'], 422);
        }

        $vendorId = $this->vendorId();

        // try {
            DB::transaction(function () use ($request, $subOrder, $vendorId) {
                $fromStatus = $subOrder->status->value;

                // 1. Update sub_order
                $method = $subOrder->shippingMethod;
                $timezone = $subOrder->warehouse?->country?->timezone ?? 'Asia/Dubai';

                $estimatedDelivery = $method
                    ? $method->computeEstimatedDeliveryDate($timezone)->toDateString()
                    : now()->addDays(5)->toDateString();

                $subOrder->update([
                    'status' => 'shipped',
                    'tracking_number' => $request->input('tracking_number'),
                    'estimated_delivery_date' => $estimatedDelivery,
                    'shipped_at' => now(),
                ]);

                // 2. Create shipment record — batch-load variant weights to avoid N+1
                $variantIds = $subOrder->items->pluck('product_variant_id');
                $variantWeights = \App\Models\ProductVariant::whereIn('id', $variantIds)
                    ->pluck('weight_grams', 'id');
                $weightGrams = $subOrder->items->sum(
                    fn($item) => ($variantWeights[$item->product_variant_id] ?? 0) * $item->quantity
                );

                $shipment = Shipment::create([
                    'sub_order_id' => $subOrder->id,
                    'carrier_id' => $subOrder->carrier_id,
                    'tracking_number' => $request->input('tracking_number'),
                    'weight_grams' => $weightGrams,
                    'shipping_cost_actual' => $subOrder->shipping,
                    'status' => 'label_created',
                ]);

                // Notify all active supervisors across all companies — no shipping_company_id
                // on shipments yet, so any company's supervisors may claim this shipment.
                $supervisors = ShippingCompanySupervisor::receivingNotifications()->get();
                if ($supervisors->isNotEmpty()) {
                    Notification::send($supervisors, new NewUnassignedShipmentArrived($shipment));
                }

                // 3. Inventory movements + decrement
                foreach ($subOrder->items as $item) {
                    $vendorListing = VendorListing::where('product_variant_id', $item->product_variant_id)
                        ->where('vendor_id', $vendorId)
                        ->first();

                    if (!$vendorListing)
                        continue;

                    $inventory = WarehouseInventory::where('vendor_listing_id', $vendorListing->id)
                        ->where('warehouse_id', $subOrder->warehouse_id)
                        ->first();

                    if (!$inventory)
                        continue;

                    $newOnHand = max(0, $inventory->quantity_on_hand - $item->quantity);
                    $newReserved = max(0, $inventory->quantity_reserved - $item->quantity);

                    $inventory->update([
                        'quantity_on_hand' => $newOnHand,
                        'quantity_reserved' => $newReserved,
                    ]);

                    InventoryMovement::create([
                        'warehouse_inventory_id' => $inventory->id,
                        'movement_type' => InventoryMovementType::Outbound->value,
                        'quantity_delta' => -$item->quantity,
                        'quantity_after' => $newOnHand,
                        'reference_type' => InventoryMovementReferenceType::Order->value,
                        'reference_id' => $subOrder->id,
                        'reason' => 'order_shipped',
                        'created_by_user_id' => Auth::guard('vendor')->user()->id,
                    ]);
                }

                // 4. Status history
                OrderStatusHistory::create([
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'shipped',
                    'changed_by_admin_id' => null,
                    'metadata' => json_encode([
                        'vendor_id' => $vendorId,
                        'vendor_admin' => Auth::guard('vendor')->user()->id,
                        'tracking_number' => $request->input('tracking_number'),
                        'carrier_id' => $subOrder->carrier_id,
                        'action' => 'shipped',
                    ]),
                ]);

                // 5. TODO: Dispatch OrderShipped notification to customer
                Log::info('SubOrder shipped by vendor', [
                    'sub_order' => $subOrder->sub_order_number,
                    'vendor_id' => $vendorId,
                    'tracking' => $request->input('tracking_number'),
                ]);
            });
        // } catch (\Throwable $e) {
        //     Log::error('Ship action failed', ['error' => $e->getMessage(), 'sub_order' => $subOrderNumber]);
        //     return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء تحديث الشحن. يرجى المحاولة مرة أخرى.'], 500);
        // }

        return response()->json(['success' => true, 'message' => 'تم تأكيد الشحن بنجاح.']);
    }

    public function shipPreview(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);
        $subOrder->loadMissing(['shippingMethod', 'warehouse.country']);

        $method = $subOrder->shippingMethod;
        $timezone = $subOrder->warehouse?->country?->timezone ?? 'Asia/Dubai';

        if (!$method) {
            return response()->json([
                'has_estimate' => false,
                'label' => null,
                'date' => null,
            ]);
        }

        return response()->json([
            'has_estimate' => true,
            'label' => $method->deliveryWindowLabel($timezone),
            'date' => $method->computeEstimatedDeliveryDate($timezone)->format('D, M j, Y'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cancel
    // ─────────────────────────────────────────────────────────────────────────

    public function cancel(Request $request, string $subOrderNumber): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $subOrder = $this->vendorSubOrder($subOrderNumber);

        $nonCancellableStatuses = ['shipped', 'delivered', 'completed', 'cancelled', 'return_requested', 'returned'];
        if (in_array($subOrder->status->value, $nonCancellableStatuses)) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إلغاء هذا الطلب في حالته الحالية.'], 422);
        }

        $vendorId = $this->vendorId();

        try {
            DB::transaction(function () use ($request, $subOrder, $vendorId) {
                $fromStatus = $subOrder->status->value;

                $subOrder->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->input('reason'),
                ]);

                // Release reserved inventory
                foreach ($subOrder->items as $item) {
                    $vendorListing = VendorListing::where('product_variant_id', $item->product_variant_id)
                        ->where('vendor_id', $vendorId)
                        ->first();

                    if (!$vendorListing)
                        continue;

                    $inventory = WarehouseInventory::where('vendor_listing_id', $vendorListing->id)
                        ->where('warehouse_id', $subOrder->warehouse_id)
                        ->first();

                    if (!$inventory)
                        continue;

                    $newReserved = max(0, $inventory->quantity_reserved - $item->quantity);
                    $inventory->update(['quantity_reserved' => $newReserved]);

                    InventoryMovement::create([
                        'warehouse_inventory_id' => $inventory->id,
                        'movement_type' => 'reservation_release',
                        'quantity_delta' => $item->quantity,
                        'quantity_after' => $inventory->quantity_on_hand,
                        'reference_type' => 'order',
                        'reference_id' => $subOrder->id,
                        'reason' => 'order_cancelled_vendor',
                        'created_by_user_id' => Auth::guard('vendor')->user()->id,
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'cancelled',
                    'changed_by_admin_id' => null,
                    'reason' => $request->input('reason'),
                    'metadata' => json_encode([
                        'vendor_id' => $vendorId,
                        'vendor_admin' => Auth::guard('vendor')->user()->id,
                        'reason' => $request->input('reason'),
                        'notes' => $request->input('reason_notes'),
                        'action' => 'cancelled',
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Cancel order failed', ['error' => $e->getMessage(), 'sub_order' => $subOrderNumber]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إلغاء الطلب.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'تم إلغاء الطلب بنجاح.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mark Out for Delivery
    // ─────────────────────────────────────────────────────────────────────────

    public function markOutForDelivery(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if ($subOrder->status !== \App\Enums\SubOrderStatus::Shipped) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تحديث هذا الطلب في حالته الحالية.'], 422);
        }

        try {
            DB::transaction(function () use ($subOrder) {
                $fromStatus = $subOrder->status->value;
                $subOrder->update(['status' => 'out_for_delivery']);

                OrderStatusHistory::create([
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'out_for_delivery',
                    'changed_by_admin_id' => null,
                    'metadata' => json_encode([
                        'vendor_id' => $this->vendorId(),
                        'vendor_admin' => Auth::guard('vendor')->user()->id,
                        'action' => 'marked_out_for_delivery',
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Mark out-for-delivery failed', ['error' => $e->getMessage(), 'sub_order' => $subOrderNumber]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء التحديث.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'تم تحديث الحالة إلى "في التوصيل".']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mark Delivered
    // ─────────────────────────────────────────────────────────────────────────

    public function markDelivered(Request $request, string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if (!in_array($subOrder->status->value, ['shipped', 'out_for_delivery'])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تحديث هذا الطلب في حالته الحالية.'], 422);
        }

        try {
            DB::transaction(function () use ($subOrder) {
                $fromStatus = $subOrder->status->value;
                $subOrder->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

                foreach ($subOrder->items as $item) {
                    $item->update([
                        'fulfillment_status' => 'delivered',
                        'return_eligible_until' => now()->addDays(14),
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'delivered',
                    'changed_by_admin_id' => null,
                    'metadata' => json_encode([
                        'vendor_id' => $this->vendorId(),
                        'vendor_admin' => Auth::guard('vendor')->user()->id,
                        'action' => 'marked_delivered',
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Mark delivered failed', ['error' => $e->getMessage(), 'sub_order' => $subOrderNumber]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء التحديث.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'تم تأكيد التسليم بنجاح.']);
    }
}
