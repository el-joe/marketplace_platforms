<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Customer\HelpCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    public function __construct(
        private readonly HelpCenterService $helpCenter,
    ) {}

    /**
     * GET {country}/help-center/tree
     *
     * Nested help-center categories (one level of sub-categories) with each
     * leaf category's published articles, for the customer help sheet.
     * Replaces the frontend's static mock tree — see enhancement.md P-26.
     *
     * `help_center_categories`/`help_center_articles` carry no context/scope
     * column, so a `context` query param has nothing to filter against; it
     * is accepted (ignored) rather than invented as a fake filter.
     */
    public function tree(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        return ApiResponse::success($this->helpCenter->getTree($country));
    }
}
