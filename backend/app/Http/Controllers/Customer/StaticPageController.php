<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\StaticPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\StaticPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaticPageController extends Controller
{
    /**
     * GET /api/customer/v1/{country}/static-pages/{slug}
     *
     * Public, admin-managed content page (About Us, Terms, Privacy, ...) for the
     * storefront: only active pages whose audience is storefront or both. The
     * country's own page wins over the global page with the same slug.
     */
    public function show(Request $request, mixed $country, string $slug): JsonResponse
    {
        $page = StaticPage::resolveForCountry(
            $slug,
            $request->attributes->get('country')?->id,
            StaticPage::AUDIENCE_STOREFRONT,
        );

        if (! $page) {
            return ApiResponse::error(__('common.exceptions.page.not_found'), [], 404);
        }

        return ApiResponse::success(new StaticPageResource($page));
    }
}
