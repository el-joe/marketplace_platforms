<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\WarrantyClaimMessageRequest;
use App\Http\Requests\Api\Customer\WarrantyClaimStoreRequest;
use App\Http\Resources\Api\Customer\WarrantyPlanResource;
use App\Http\Resources\Customer\WarrantyClaimMessageResource;
use App\Http\Resources\Customer\WarrantyClaimResource;
use App\Http\Resources\Customer\WarrantyPurchaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\WarrantyClaim;
use App\Models\WarrantyPlan;
use App\Models\WarrantyPurchase;
use App\Notifications\Admin\NewWarrantyClaimNotification as AdminNewWarrantyClaimNotification;
use App\Notifications\Vendor\NewWarrantyClaimNotification as VendorNewWarrantyClaimNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class WarrantyController extends Controller
{
    public function __construct(
        private readonly \App\Services\WarrantyPlanService $warrantyPlanService,
    ) {
    }

    public function plans(string $orderItemId): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $orderItem = OrderItem::with(['order', 'productVariant.product'])
            ->find($orderItemId);

        if (! $orderItem || ! $orderItem->order || $orderItem->order->customer_id !== $customer->id) {
            return ApiResponse::error(__('customer_api.warranty.order_item_not_found'), [], 404);
        }

        if ($orderItem->warranty_purchase_id !== null) {
            return ApiResponse::success([], __('customer_api.warranty.already_has_warranty'));
        }

        $product = $orderItem->productVariant?->product;

        if (! $product) {
            return ApiResponse::success([], __('customer_api.warranty.no_plans_available'));
        }

        $plans = $this->warrantyPlanService->getPlansForProduct(
            $product,
            $customer->country_id,
            $orderItem->order->currency,
        );

        return ApiResponse::success($plans, __('customer_api.warranty.plans_retrieved'));
    }

    public function purchases(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = WarrantyPurchase::forCustomer($customer->id)
            ->active()
            ->with(['orderItem', 'plan'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($paginator, WarrantyPurchaseResource::class);
    }

    public function claimsIndex(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = WarrantyClaim::where('customer_id', $customer->id)
            ->with(['product', 'vendor'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::paginated($paginator, WarrantyClaimResource::class);
    }

    public function claimsStore(WarrantyClaimStoreRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $orderItem = OrderItem::with(['order', 'subOrder', 'productVariant', 'warrantyPurchase'])
            ->findOrFail($request->validated('order_item_id'));

        $productId = $orderItem->productVariant?->product_id
            ?? $orderItem->product_snapshot['product_id'] ?? null;

        $listingType = $orderItem->vendor_listing_id !== null
            ? WarrantyClaim::LISTING_TYPE_VENDOR
            : WarrantyClaim::LISTING_TYPE_ADMIN;

        $claim = WarrantyClaim::create([
            'claim_number' => 'WC-'.strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $productId,
            'vendor_id' => $orderItem->vendor_id,
            'listing_type' => $listingType,
            'issue_type' => $request->validated('issue_type'),
            'issue_description' => $request->validated('issue_description'),
            'purchase_date' => ($orderItem->subOrder?->delivered_at
                ?? $orderItem->order->completed_at
                ?? $orderItem->order->placed_at)?->toDateString(),
            'warranty_expires_at' => $orderItem->warrantyPurchase->coverage_ends_at,
            'covered_by_platform_warranty' => $orderItem->warrantyPurchase !== null,
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
            __('customer_api.warranty.claim_submitted'),
            201,
        );
    }

    public function claimsShow(string $claimNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $claim = WarrantyClaim::where('claim_number', $claimNumber)
            ->where('customer_id', $customer->id)
            ->with([
                'product',
                'vendor',
                'messages' => fn ($q) => $q->where('is_internal_note', false)->orderBy('created_at'),
            ])
            ->first();

        if (! $claim) {
            return ApiResponse::error(__('customer_api.warranty.claim_not_found'), [], 404);
        }

        return ApiResponse::success(new WarrantyClaimResource($claim));
    }

    public function claimsAddMessage(WarrantyClaimMessageRequest $request, string $claimNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $claim = WarrantyClaim::where('claim_number', $claimNumber)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $claim) {
            return ApiResponse::error(__('customer_api.warranty.claim_not_found'), [], 404);
        }

        if (in_array($claim->status, [WarrantyClaim::STATUS_RESOLVED, WarrantyClaim::STATUS_REJECTED], true)) {
            return ApiResponse::error(__('customer_api.warranty.claim_closed'), [], 422);
        }

        $message = $claim->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => $request->validated('message'),
            'is_internal_note' => false,
        ]);

        return ApiResponse::success(new WarrantyClaimMessageResource($message), __('customer_api.warranty.message_sent'), 201);
    }
}
