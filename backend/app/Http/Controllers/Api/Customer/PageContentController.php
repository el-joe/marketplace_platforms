<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\BannerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\Faq;
use App\Services\BannerService;
use App\Support\SafeCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageContentController extends Controller
{
    public function __construct(
        private readonly BannerService $bannerService,
    ) {
    }

    /**
     * GET /v1/{country}/page-content/gift-cards
     *
     * Public, read-only, country-scoped content backing the gift cards
     * landing page: the hero + redeem banners (admin-managed via the Banner
     * CMS) and the gift-cards FAQ list (admin-managed via the Faq CMS).
     * Cached briefly since this is a public, low-churn page.
     */
    public function giftCards(Request $request): JsonResponse
    {
        /** @var Country $country */
        $country = $request->attributes->get('country');

        $data = SafeCache::remember("page_content_gift_cards_{$country->id}", 300, function () use ($country) {
            $heroBanner = $this->bannerService->getActivePlacement('gift_cards_hero', $country->id);
            $redeemBanner = $this->bannerService->getActivePlacement('gift_cards_redeem', $country->id);

            $faqs = Faq::query()
                ->active()
                ->forContext('gift_cards')
                ->orderBy('sort_order')
                ->get();

            return [
                'banners' => [
                    'gift_cards_hero' => $heroBanner ? (new BannerResource($heroBanner))->resolve() : null,
                    'gift_cards_redeem' => $redeemBanner ? (new BannerResource($redeemBanner))->resolve() : null,
                ],
                'faqs' => $faqs->map(fn (Faq $faq) => [
                    'id' => $faq->id,
                    'question_en' => $faq->question_en,
                    'question_ar' => $faq->question_ar,
                    'answer_en' => $faq->answer_en,
                    'answer_ar' => $faq->answer_ar,
                    'sort_order' => $faq->sort_order,
                ])->values(),
            ];
        });

        return ApiResponse::success($data);
    }
}
