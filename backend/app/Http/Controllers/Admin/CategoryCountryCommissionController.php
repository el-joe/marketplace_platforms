<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CountryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryCountryCommissionController extends Controller
{
    /**
     * Upsert country-level FBP/FBM/FBN commission overrides for one category + country.
     * NULL values mean "inherit from category default".
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $data = $request->validate([
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'commission_fbp_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fbp_fixed' => ['nullable', 'numeric', 'min:0'],
            'commission_fbm_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fbm_fixed' => ['nullable', 'numeric', 'min:0'],
            'commission_fbn_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fbn_fixed' => ['nullable', 'numeric', 'min:0'],
        ]);

        CountryCategory::updateOrCreate(
            [
                'country_id' => $data['country_id'],
                'category_id' => $categoryModel->id,
            ],
            [
                'commission_fbp_pct' => $data['commission_fbp_pct'] ?? null,
                'commission_fbp_fixed' => isset($data['commission_fbp_fixed']) ? round((float) $data['commission_fbp_fixed'], 4) : null,
                'commission_fbm_pct' => $data['commission_fbm_pct'] ?? null,
                'commission_fbm_fixed' => isset($data['commission_fbm_fixed']) ? round((float) $data['commission_fbm_fixed'], 4) : null,
                'commission_fbn_pct' => $data['commission_fbn_pct'] ?? null,
                'commission_fbn_fixed' => isset($data['commission_fbn_fixed']) ? round((float) $data['commission_fbn_fixed'], 4) : null,
                'updated_by_admin_id' => Auth::guard('admin')->id(),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
