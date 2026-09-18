<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\WarrantyClaimMessageRequest;
use App\Http\Requests\Api\Customer\WarrantyClaimStoreRequest;
use App\Http\Requests\Api\Customer\WarrantyPurchaseStoreRequest;
use App\Http\Resources\Api\Customer\WarrantyPlanResource;
use App\Http\Resources\Customer\WarrantyClaimMessageResource;
use App\Http\Resources\Customer\WarrantyClaimResource;
use App\Http\Resources\Customer\WarrantyPurchaseResource;
use App\Http\Responses\ApiResponse;
use App\Enums\WalletOwnerType;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WarrantyClaim;
use App\Models\WarrantyPlan;
use App\Models\WarrantyPurchase;
use App\Exceptions\InsufficientBalanceException;
use App\Notifications\Admin\NewWarrantyClaimNotification as AdminNewWarrantyClaimNotification;
use App\Notifications\Vendor\NewWarrantyClaimNotification as VendorNewWarrantyClaimNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
            (int) $orderItem->unit_price,
        );

        return ApiResponse::success($plans, __('customer_api.warranty.plans_retrieved'));
    }

    public function purchases(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        // FIX-6: a warranty purchased at checkout starts life `pending` and
        // is only flipped to `active` (with real coverage dates) by
        // SubOrderObserver once the sub-order is delivered. Filtering to
        // ->active() only hid every newly-purchased warranty from "My
        // Warranties" until delivery, which read as "I bought a warranty
        // and it never showed up." Show pending ones too, as
        // upcoming/not-yet-active — WarrantyPurchaseResource marks them
        // clearly non-claimable via `is_claimable`/`status` rather than
        // dropping them from the list.
        $paginator = WarrantyPurchase::forCustomer($customer->id)
            ->whereIn('status', ['pending', 'active'])
            ->with(['orderItem', 'plan', 'product.images'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($paginator, WarrantyPurchaseResource::class);
    }

    /**
     * enhancement.md P-09 task 4: "buy a warranty after delivery". The
     * request already validated ownership, delivery, the purchase window
     * (config('warranty.post_purchase_window_days')) and that the item has
     * no pending/active warranty. Pricing reuses WarrantyPlan::resolvePrice()
     * (the same flat-vs-percentage calculation checkout uses via
     * CheckoutPricingEngine::resolveWarrantySelections()). Payment is taken
     * from the customer wallet (Wallet, owner_type=customer — the same
     * primitive checkout wallet payments use) — no warranty_purchases row is created
     * unless the debit succeeds, so a failed payment never leaves an
     * active-without-payment warranty. Since the item is already delivered,
     * the purchase is activated immediately (not queued for a delivery
     * event) using the exact same coverage-date formula as
     * SubOrderObserver (WarrantyPurchase::coverageDatesFor()).
     */
    public function purchasesStore(WarrantyPurchaseStoreRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $orderItem = OrderItem::with(['order', 'subOrder.vendor', 'productVariant'])
            ->findOrFail($request->validated('order_item_id'));

        $plan = WarrantyPlan::findOrFail($request->validated('warranty_plan_id'));

        $price = $plan->resolvePrice((int) $orderItem->unit_price);
        $currency = $orderItem->order->currency;

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $customer->id)
            ->first();

        if (! $wallet || $wallet->currency !== $currency) {
            return ApiResponse::error(__('customer_api.warranty.wallet_currency_mismatch'), [], 422);
        }

        try {
            DB::transaction(function () use ($wallet, $price, $orderItem, $plan, $customer, $currency, &$warrantyPurchase): void {
                // Debit first: if the wallet has insufficient balance this
                // throws and the transaction rolls back before any
                // warranty_purchases row is ever created.
                $lockedWallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                if ($lockedWallet->balance < $price) {
                    throw new InsufficientBalanceException($price, $lockedWallet->balance, $lockedWallet->currency);
                }

                $lockedWallet->update(['balance' => $lockedWallet->balance - $price]);
                $wallet->setAttribute('balance', $lockedWallet->balance);

                $deliveredAt = $orderItem->subOrder->delivered_at;
                $vendorWarrantyMonths = $orderItem->subOrder?->vendor?->warranty_months
                    ? (int) $orderItem->subOrder->vendor->warranty_months
                    : null;

                $dates = WarrantyPurchase::coverageDatesFor($deliveredAt, $vendorWarrantyMonths, (int) $plan->duration_months);

                $warrantyPurchase = WarrantyPurchase::create([
                    'customer_id' => $customer->id,
                    'order_id' => $orderItem->order_id,
                    'order_item_id' => $orderItem->id,
                    // FIX-6: real link to the product being covered.
                    'product_id' => $orderItem->productVariant?->product_id,
                    'warranty_plan_id' => $plan->id,
                    'plan_snapshot' => [
                        'name_en' => $plan->name_en,
                        'name_ar' => $plan->name_ar,
                        'duration_months' => $plan->duration_months,
                        'features_en' => $plan->features_en,
                        'features_ar' => $plan->features_ar,
                        'price' => $plan->price,
                        'price_type' => $plan->price_type,
                        'price_pct' => $plan->price_pct,
                        'currency' => $plan->currency,
                        'resolved_price' => $price,
                    ],
                    'price_paid' => $price,
                    'currency' => $currency,
                    // Activated immediately: the item is already delivered,
                    // so there is no future delivery event to trigger
                    // SubOrderObserver's activation path.
                    'status' => 'active',
                    'coverage_starts_at' => $dates['starts']->toDateString(),
                    'coverage_ends_at' => $dates['ends']->toDateString(),
                ]);

                $orderItem->update(['warranty_purchase_id' => $warrantyPurchase->id]);

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'customer_id' => $customer->id,
                    'type' => 'warranty_purchase',
                    'direction' => 'debit',
                    'amount' => $price,
                    'balance_after' => $wallet->balance,
                    'currency_code' => $currency,
                    'reference_type' => WarrantyPurchase::class,
                    'reference_id' => $warrantyPurchase->id,
                    'source_type' => 'warranty_purchase',
                    'source_id' => $warrantyPurchase->id,
                    'description' => 'Post-purchase warranty: '.$plan->name_en,
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return ApiResponse::error(__('customer_api.warranty.insufficient_wallet_balance'), [], 422);
        }

        return ApiResponse::success(
            new WarrantyPurchaseResource($warrantyPurchase->load(['orderItem', 'plan'])),
            __('customer_api.warranty.purchase_created'),
            201,
        );
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

        $orderItem = OrderItem::with(['order', 'subOrder.vendor', 'productVariant', 'warrantyPurchase'])
            ->findOrFail($request->validated('order_item_id'));

        $productId = $orderItem->productVariant?->product_id
            ?? $orderItem->product_snapshot['product_id'] ?? null;

        $listingType = match (true) {
            $orderItem->vendor_listing_id !== null => WarrantyClaim::LISTING_TYPE_VENDOR,
            $orderItem->marketer_listing_id !== null => WarrantyClaim::LISTING_TYPE_MARKETER,
            default => WarrantyClaim::LISTING_TYPE_ADMIN,
        };

        $warrantyPurchase = $orderItem->warrantyPurchase;
        $hasActivePlatformWarranty = $warrantyPurchase
            && $warrantyPurchase->status === 'active'
            && $warrantyPurchase->coverage_ends_at
            && $warrantyPurchase->coverage_ends_at->gte(today());

        $deliveredAt = $orderItem->subOrder?->delivered_at
            ?? $orderItem->order->completed_at
            ?? $orderItem->order->placed_at;

        $vendorWarrantyMonths = $orderItem->subOrder?->vendor?->warranty_months;
        $brandWindowEnds = $vendorWarrantyMonths && $deliveredAt
            ? $deliveredAt->copy()->addMonths((int) $vendorWarrantyMonths)
            : null;

        // enhancement.md P-09: a claim without a platform warranty falls
        // back to the brand window, and never crashes reading
        // coverage_ends_at off a null warrantyPurchase.
        $warrantyExpiresAt = $hasActivePlatformWarranty
            ? $warrantyPurchase->coverage_ends_at
            : $brandWindowEnds;

        $claim = WarrantyClaim::create([
            'claim_number' => 'WC-'.strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'warranty_purchase_id' => $hasActivePlatformWarranty ? $warrantyPurchase->id : null,
            'product_id' => $productId,
            'vendor_id' => $orderItem->vendor_id,
            'listing_type' => $listingType,
            'claim_type' => $hasActivePlatformWarranty ? WarrantyClaim::CLAIM_TYPE_PLATFORM : WarrantyClaim::CLAIM_TYPE_BRAND,
            'issue_type' => $request->validated('issue_type'),
            'issue_description' => $request->validated('issue_description'),
            'purchase_date' => $deliveredAt?->toDateString(),
            'warranty_expires_at' => $warrantyExpiresAt,
            'covered_by_platform_warranty' => $hasActivePlatformWarranty,
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

        if (\Spatie\Permission\Models\Permission::where('name', 'warranty_claims.manage')->where('guard_name', 'admin')->exists()) {
            Notification::send(
                Admin::permission('warranty_claims.manage')->get(),
                new AdminNewWarrantyClaimNotification($claim),
            );
        }

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
