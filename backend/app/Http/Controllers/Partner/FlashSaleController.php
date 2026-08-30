<?php

namespace App\Http\Controllers\Partner;

use App\Enums\FlashSaleStatus;
use App\Enums\FlashSaleSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSalePriceHistory;
use App\Models\FlashSaleSubmission;
use App\Models\FlashSaleVendorInvitition;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FlashSaleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    /**
     * Find this vendor's invitation to a flash sale or abort 404.
     * NOTE: table/model name preserves the intentional typo "invititions".
     */
    private function findInvitation(string $flashSaleId): FlashSaleVendorInvitition
    {
        return FlashSaleVendorInvitition::where('flash_sale_id', $flashSaleId)
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $vendorId = $this->vendorId();

        $invitations = FlashSaleVendorInvitition::where('vendor_id', $vendorId)
            ->with(['flashSale.country'])
            ->get();

        // Count submissions per flash sale for this vendor
        $flashSaleIds = $invitations->pluck('flash_sale_id');

        $submissionCounts = FlashSaleSubmission::where('vendor_id', $vendorId)
            ->whereIn('flash_sale_id', $flashSaleIds)
            ->selectRaw('flash_sale_id, COUNT(*) as total')
            ->groupBy('flash_sale_id')
            ->pluck('total', 'flash_sale_id');

        foreach ($invitations as $inv) {
            $inv->submissions_count = (int) $submissionCounts->get($inv->flash_sale_id, 0);
        }

        $grouped = [
            'upcoming' => $invitations->filter(
                fn($i) => in_array($i->flashSale?->status, [FlashSaleStatus::Approved, FlashSaleStatus::SubmissionClosed, FlashSaleStatus::UnderReview])
            )->values(),
            'open' => $invitations->filter(
                fn($i) => $i->flashSale?->status === FlashSaleStatus::SubmissionOpen
            )->values(),
            'live' => $invitations->filter(
                fn($i) => $i->flashSale?->status === FlashSaleStatus::Live
            )->values(),
            'ended' => $invitations->filter(
                fn($i) => in_array($i->flashSale?->status, [FlashSaleStatus::Ended, FlashSaleStatus::Cancelled])
            )->values(),
        ];

        return view('partner.flash-sales.index', compact('grouped'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $flashSaleId): View
    {
        $flashSale = FlashSale::with('country')->findOrFail($flashSaleId);
        $invitation = $this->findInvitation($flashSaleId);

        $submissions = FlashSaleSubmission::where('flash_sale_id', $flashSaleId)
            ->where('vendor_id', $this->vendorId())
            ->with('vendorListing.productVariant.product')
            ->orderByDesc('submitted_at')
            ->get();

        return view('partner.flash-sales.show', compact('flashSale', 'invitation', 'submissions'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Eligible Listings (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function eligibleListings(string $flashSaleId): JsonResponse
    {
        $flashSale = FlashSale::findOrFail($flashSaleId);
        $this->findInvitation($flashSaleId);

        $query = VendorListing::where('vendor_id', $this->vendorId())
            ->where('status', 'active')
            ->with(['productVariant.product']);

        // Scope to flash sale's country if set (null = all countries)
        if ($flashSale->country_id) {
            $query->where('country_id', $flashSale->country_id);
        }

        // Scope to eligible categories if set (null = all categories)
        if ($flashSale->eligible_categories) {
            $eligible = $flashSale->eligible_categories;
            $query->whereHas('productVariant.product', function ($q) use ($eligible) {
                $q->whereIn('category_id', $eligible);
            });
        }

        $listings = $query->get();

        // Exclude listings already actively submitted for this flash sale
        $submittedIds = FlashSaleSubmission::where('flash_sale_id', $flashSaleId)
            ->where('vendor_id', $this->vendorId())
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->pluck('vendor_listing_id')
            ->all();

        $cutoff = now()->subDays(30);

        $result = $listings
            ->reject(fn($l) => in_array($l->id, $submittedIds))
            ->map(function (VendorListing $listing) use ($cutoff) {
                $avg30d = FlashSalePriceHistory::where('vendor_listing_id', $listing->id)
                    ->where('recorded_at', '>=', $cutoff)
                    ->avg('price');

                $product = $listing->productVariant?->product;

                return [
                    'id' => $listing->id,
                    'product_name' => $product?->name_ar ?? $product?->name_en ?? 'منتج',
                    'sku' => $listing->vendor_sku,
                    'currency' => $listing->currency,
                    'current_price' => $listing->price,
                    'current_price_display' => round($listing->price, 2),
                    'avg_price_30d' => $avg30d ? (int) round($avg30d) : null,
                    'avg_price_30d_display' => $avg30d ? round($avg30d, 2) : null,
                ];
            })
            ->values();

        return response()->json(['listings' => $result]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Submit
    // ─────────────────────────────────────────────────────────────────────────

    public function submit(Request $request, string $flashSaleId): JsonResponse
    {
        $flashSale = FlashSale::findOrFail($flashSaleId);

        if ($flashSale->status !== FlashSaleStatus::SubmissionOpen) {
            return response()->json(['message' => 'هذا العرض لا يقبل طلبات حالياً.'], 422);
        }

        $invitation = $this->findInvitation($flashSaleId);

        $validated = $request->validate([
            'vendor_listing_id' => ['required', 'uuid'],
            'flash_price' => ['required', 'integer', 'min:1'],
            'original_price' => ['required', 'integer', 'min:1'],
            'max_quantity_total' => ['required', 'integer', 'min:1'],
            'max_quantity_per_customer' => ['nullable', 'integer', 'min:1'],
            'vendor_notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Ownership check
        $listing = VendorListing::where('id', $validated['vendor_listing_id'])
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();

        // Duplicate submission check
        $alreadySubmitted = FlashSaleSubmission::where('flash_sale_id', $flashSaleId)
            ->where('vendor_listing_id', $listing->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->exists();

        if ($alreadySubmitted) {
            return response()->json(['message' => 'هذا المنتج مُقدَّم بالفعل لهذا العرض.'], 422);
        }

        $flashPrice = (int) $validated['flash_price'];
        $originalPrice = (int) $validated['original_price'];

        if ($flashPrice >= $originalPrice) {
            return response()->json(['message' => 'سعر الفلاش يجب أن يكون أقل من السعر الأصلي.'], 422);
        }

        $discountPct = (($originalPrice - $flashPrice) / $originalPrice) * 100;

        if ($discountPct < (float) $flashSale->min_discount_pct) {
            return response()->json([
                'message' => 'الخصم المطلوب ' . number_format($flashSale->min_discount_pct, 0) . '% على الأقل.',
            ], 422);
        }

        // 30-day reference price
        $avg30d = FlashSalePriceHistory::where('vendor_listing_id', $listing->id)
            ->where('recorded_at', '>=', now()->subDays(30))
            ->avg('price');
        $referencePrice30d = $avg30d ? (int) round($avg30d) : $originalPrice;

        if ($flashSale->price_drop_required && $flashPrice >= $referencePrice30d) {
            return response()->json([
                'message' => 'سعر الفلاش يجب أن يكون أقل من متوسط السعر خلال 30 يومًا الماضية.',
            ], 422);
        }

        // ⚠ quantity_remaining MUST NOT be in this create() call — it is a MySQL VIRTUAL column
        $submission = FlashSaleSubmission::create([
            'flash_sale_id' => $flashSaleId,
            'vendor_listing_id' => $listing->id,
            'vendor_id' => $this->vendorId(),
            'status' => 'submitted',
            'flash_price' => $flashPrice,
            'original_price' => $originalPrice,
            'calculated_discount_pct' => round($discountPct, 2),
            'reference_price_30d' => $referencePrice30d,
            'flash_price_currency' => $listing->currency,
            'max_quantity_total' => $validated['max_quantity_total'],
            'max_quantity_per_customer' => $validated['max_quantity_per_customer'] ?? null,
            'vendor_notes' => $validated['vendor_notes'] ?? null,
            'submitted_at' => now(),
        ]);

        // Update invitation status if pending/accepted
        if (in_array($invitation->status?->value, ['pending', 'accepted'])) {
            $invitation->update([
                'status' => 'submitted',
                'responded_at' => $invitation->responded_at ?? now(),
            ]);
        }

        return response()->json([
            'message' => 'تم تقديم المنتج بنجاح.',
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status->value,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Live Stats (AJAX — polled every 10 seconds)
    // ─────────────────────────────────────────────────────────────────────────

    public function liveStats(string $flashSaleId): JsonResponse
    {
        $flashSale = FlashSale::findOrFail($flashSaleId);

        if (!$flashSale->isLive()) {
            return response()->json(['message' => 'البيع الفوري غير نشط حالياً.'], 422);
        }

        $this->findInvitation($flashSaleId);

        // quantity_remaining is a VIRTUAL column — safe to SELECT, never INSERT/UPDATE
        $submissions = FlashSaleSubmission::where('flash_sale_id', $flashSaleId)
            ->where('vendor_id', $this->vendorId())
            ->whereIn('status', [FlashSaleSubmissionStatus::Approved->value, FlashSaleSubmissionStatus::Live->value, FlashSaleSubmissionStatus::SoldOut->value])
            ->get([
                'id',
                'vendor_listing_id',
                'flash_price',
                'flash_price_currency',
                'quantity_sold',
                'quantity_remaining',
                'max_quantity_total',
                'status',
            ]);

        $stats = $submissions->map(function (FlashSaleSubmission $sub) {
            return [
                'submission_id' => $sub->id,
                'vendor_listing_id' => $sub->vendor_listing_id,
                'status' => $sub->status->value,
                'quantity_sold' => (int) $sub->quantity_sold,
                'quantity_remaining' => (int) $sub->quantity_remaining,
                'max_quantity_total' => (int) $sub->max_quantity_total,
                'revenue' => (int) $sub->flash_price * (int) $sub->quantity_sold,
                'revenue_display' => round(((int) $sub->flash_price * (int) $sub->quantity_sold), 2),
                'currency' => $sub->flash_price_currency,
            ];
        });

        return response()->json([
            'flash_sale_id' => $flashSaleId,
            'ends_at' => $flashSale->sale_ends_at?->toIso8601String(),
            'time_remaining_s' => $flashSale->time_remaining_seconds,
            'submissions' => $stats,
        ]);
    }
}
