<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\StaticPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\StaticPage;
use Illuminate\Http\JsonResponse;

class StaticPageController extends Controller
{
    /**
     * GET /api/customer/v1/{country}/static-pages/{slug}
     *
     * Public, admin-managed content page (About Us, Terms, Privacy, ...) for the
     * storefront: only active pages whose audience is storefront or both.
     */
    public function show(mixed $country, string $slug): JsonResponse
    {
        $page = StaticPage::query()
            ->forStorefront()
            ->active()
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            return ApiResponse::error(__('common.exceptions.page.not_found'), [], 404);
        }

        return ApiResponse::success(new StaticPageResource($page));
    }
}
