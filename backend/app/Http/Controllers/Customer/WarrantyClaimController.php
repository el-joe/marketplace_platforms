<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreWarrantyClaimRequest;
use App\Http\Requests\Customer\WarrantyClaimMessageRequest;
use App\Http\Resources\Customer\WarrantyClaimMessageResource;
use App\Http\Resources\Customer\WarrantyClaimResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\WarrantyClaim;
use App\Notifications\Admin\NewWarrantyClaimNotification as AdminNewWarrantyClaimNotification;
use App\Notifications\Vendor\NewWarrantyClaimNotification as VendorNewWarrantyClaimNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class WarrantyClaimController extends Controller
{
    public function index(string $country): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = WarrantyClaim::where('customer_id', $customer->id)
            ->with(['product', 'vendor'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::paginated($paginator, WarrantyClaimResource::class);
    }

    public function store(StoreWarrantyClaimRequest $request, string $country): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $orderItem = OrderItem::with(['order', 'subOrder', 'productVariant', 'warrantyPurchase'])
            ->findOrFail($request->validated('order_item_id'));

        $productId = $orderItem->productVariant?->product_id
            ?? $orderItem->product_snapshot['product_id'] ?? null;

        $listingType = $orderItem->vendor_listing_id !== null ? 'vendor_listing' : 'admin_listing';

        $purchaseDate = $orderItem->subOrder?->delivered_at
            ?? $orderItem->order->completed_at
            ?? $orderItem->order->created_at;

        $warrantyPurchase = $orderItem->warrantyPurchase;
        $coveredByPlatformWarranty = $warrantyPurchase !== null && $warrantyPurchase->status === 'active';

        $warrantyExpiresAt = $coveredByPlatformWarranty
            ? $warrantyPurchase->coverage_ends_at
            : $request->date('warranty_expires_at');

        $claim = WarrantyClaim::create([
            'claim_number' => 'WC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
            'customer_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $productId,
            'vendor_id' => $orderItem->vendor_id,
            'listing_type' => $listingType,
            'issue_type' => $request->validated('issue_type'),
            'issue_description' => $request->validated('issue_description'),
            'purchase_date' => $purchaseDate,
            'warranty_expires_at' => $warrantyExpiresAt,
            'covered_by_platform_warranty' => $coveredByPlatformWarranty,
            'status' => WarrantyClaim::STATUS_SUBMITTED,
        ]);

        if ($request->hasFile('evidence_files')) {
            $paths = [];

            foreach ($request->file('evidence_files') as $file) {
                $paths[] = $file->store("warranty-evidence/{$claim->id}", 'public');
            }

            $claim->update(['evidence_files' => $paths]);
        }

        if ($listingType === WarrantyClaim::LISTING_TYPE_VENDOR) {
            $claim->vendor?->loadMissing('vendorAdmins');
            Notification::send($claim->vendor?->vendorAdmins, new VendorNewWarrantyClaimNotification($claim));
        }

        Notification::send(
            Admin::permission('warranty_claims.manage')->get(),
            new AdminNewWarrantyClaimNotification($claim),
        );

        return ApiResponse::success(
            new WarrantyClaimResource($claim->load(['product', 'vendor'])),
            __('common.exceptions.warranty_claim.submitted'),
            201,
        );
    }

    public function show(string $country, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $claim = WarrantyClaim::where('id', $id)
            ->where('customer_id', $customer->id)
            ->with([
                'product',
                'vendor',
                'messages' => fn ($q) => $q->where('is_internal_note', false),
            ])
            ->first();

        if (!$claim) {
            return ApiResponse::error(__('common.exceptions.warranty_claim.not_found'), [], 404);
        }

        return ApiResponse::success(new WarrantyClaimResource($claim));
    }

    public function addMessage(WarrantyClaimMessageRequest $request, string $country, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $claim = WarrantyClaim::where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$claim) {
            return ApiResponse::error(__('common.exceptions.warranty_claim.not_found'), [], 404);
        }

        if (in_array($claim->status, [WarrantyClaim::STATUS_RESOLVED, WarrantyClaim::STATUS_REJECTED], true)) {
            return ApiResponse::error(__('common.exceptions.warranty_claim.closed'), [], 422);
        }

        $message = $claim->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => $request->validated('message'),
            'is_internal_note' => false,
        ]);

        return ApiResponse::success(new WarrantyClaimMessageResource($message), __('common.exceptions.warranty_claim.message_sent'), 201);
    }
}
