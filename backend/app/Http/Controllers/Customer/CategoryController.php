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
     * Full nested tree for nav/menu. Cached 10 min per country.
     */
    public function index(Request $request,$country): JsonResponse
    {
        $country = $request->attributes->get('country');
        return response()->json([
            'success' => true,
            'data'    => $this->categories->getTree($country),
        ]);
    }
}
