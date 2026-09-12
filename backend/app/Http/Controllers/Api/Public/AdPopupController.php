<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\VendorAdSubscription;
use Illuminate\Http\JsonResponse;

class AdPopupController extends Controller
{
    public function show(): JsonResponse
    {
        $popup = VendorAdSubscription::with(['vendorListing.productVariant.product'])
            ->active()
            ->whereHas('adPackage', fn ($q) => $q->where('tier', 'serious_featured'))
            ->whereNotNull('popup_title_en')
            ->inRandomOrder()
            ->first();

        if (!$popup) {
            return response()->json(['popup' => null]);
        }

        return response()->json([
            'popup' => [
                'id' => $popup->id,
                'title_en' => $popup->popup_title_en,
                'title_ar' => $popup->popup_title_ar,
                'body_en' => $popup->popup_body_en,
                'body_ar' => $popup->popup_body_ar,
                'image_url' => $popup->popup_image_url,
                'cta_url' => $popup->popup_cta_url,
                'product_slug' => $popup->vendorListing?->productVariant?->product?->slug,
            ],
        ]);
    }
}
