<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    /**
     * GET /categories
     * Lightweight nav tree for the storefront header. Cached 10 min per
     * (country, locale). See enhancement.md P-21: this is the CHEAP payload
     * (no filterable attributes, capped brand list) — /categories/browse
     * below carries the heavy per-category detail.
     */
    public function index(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        return response()->json([
            'success' => true,
            'data'    => $this->categories->getNavTree($country),
        ]);
    }

    /**
     * GET /categories/browse
     * Heavy browse payload: full product category tree with brand lists,
     * product counts and filterable attributes. enhancement.md P-21 task 6.
     */
    public function browse(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        return response()->json([
            'success' => true,
            'data'    => $this->categories->getBrowseTree($country),
        ]);
    }
}
