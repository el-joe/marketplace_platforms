<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ClassifiedPlacement;
use App\Http\Controllers\Controller;
use App\Services\Customer\ClassifiedSectionService;
use App\Services\Customer\ListingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassifiedSectionController extends Controller
{
    public function __construct(
        private readonly ClassifiedSectionService $sections,
        private readonly ListingQueryService $listings,
    ) {}

    /**
     * GET /api/customer/v1/{country}/classified/sections?placement=home&sections=6&per_section=8
     *
     * The classified sections the admin placed on a storefront surface, for this country, in the admin's order.
     * Each section carries its visible sub-categories and its newest ads, so the storefront renders dedicated
     * blocks purely from configuration.
     */
    public function index(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        $data = $request->validate([
            'placement' => ['nullable', Rule::in(ClassifiedPlacement::values())],
            'sections' => ['nullable', 'integer', 'min:1', 'max:12'],
            'per_section' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $placement = ClassifiedPlacement::from($data['placement'] ?? ClassifiedPlacement::Home->value);

        return response()->json([
            'success' => true,
            'data' => $this->sections->sections(
                $country,
                $placement,
                (int) ($data['sections'] ?? 6),
                (int) ($data['per_section'] ?? 8),
                $this->listings,
            ),
        ]);
    }
}
