<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\NavSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Services\Customer\UnifiedCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    private const SECTION_LABELS = [
        'products' => [
            'label' => ['ar' => 'تسوق', 'en' => 'Shop'],
        ],
        'classifieds' => [
            'label' => ['ar' => 'الإعلانات المبوّبة', 'en' => 'Classifieds'],
        ],
        'travel' => [
            'label' => ['ar' => 'السفر', 'en' => 'Travel'],
            'link'  => '/travel',
        ],
    ];

    public function __construct(
        private readonly UnifiedCategoryService $unifiedCategoryService,
    ) {}

    /**
     * GET /api/customer/v1/{country}/nav
     * Unified category nav tree: products → classifieds → travel.
     */
    public function index(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        $tree = $this->unifiedCategoryService->getMergedTree($country);

        $nav = array_map(function (array $section) {
            $labels = self::SECTION_LABELS[$section['section']] ?? [];

            return array_merge(
                ['section' => $section['section']],
                $labels,
                ['nodes' => $section['nodes']],
            );
        }, $tree);

        return ApiResponse::success([
            'nav' => NavSectionResource::collection($nav),
        ]);
    }
}
