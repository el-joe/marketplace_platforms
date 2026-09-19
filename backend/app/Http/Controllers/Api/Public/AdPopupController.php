<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Enums\PaidAdSlotTargetType;
use App\Models\PaidAdBooking;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdPopupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $candidates = [];

        $booking = PaidAdBooking::with(['currentCreative.files'])
            ->where('country_id', $request->attributes->get('country')->id)
            ->where('status', PaidAdBookingStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('booked_until')->orWhereDate('booked_until', '>=', today()))
            ->whereHas('slot', fn ($q) => $q->where('target_type', PaidAdSlotTargetType::ListingPromotion->value)
                ->where('shows_popup', true))
            ->whereHas('currentCreative', fn ($q) => $q->where('status', PaidAdCreativeStatus::Approved->value))
            ->inRandomOrder()
            ->first();

        if ($booking && ($c = $booking->currentCreative)) {
            $listing = $c->destination_reference_id
                ? VendorListing::with('productVariant.product')->find($c->destination_reference_id)
                : null;
            $candidates[] = [
                'id' => $booking->id,
                'title_en' => $c->title_en,
                'title_ar' => $c->title_ar,
                'body_en' => $c->subtitle_en,
                'body_ar' => $c->subtitle_ar,
                'cta_label_en' => $c->cta_label_en,
                'cta_label_ar' => $c->cta_label_ar,
                'image_url' => $c->imagePair('desktop')['en'],
                'image_url_ar' => $c->imagePair('desktop')['ar'],
                'image_url_mobile' => $c->imagePair('mobile')['en'],
                'image_url_mobile_ar' => $c->imagePair('mobile')['ar'],
                'cta_url' => $this->safeUrl($c->destination_url),
                'product_slug' => $listing?->productVariant?->product?->slug,
            ];
        }

        if (! $candidates) {
            return response()->json(['popup' => null]);
        }

        return response()->json(['popup' => $candidates[array_rand($candidates)]]);
    }

    /** Only http(s) absolute URLs or site-relative paths are allowed. */
    private function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
