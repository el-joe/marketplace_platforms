<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdPackageController extends Controller
{
    public function index(): View
    {
        $packages = AdPackage::ordered()->get();

        return view('admin.ad-packages.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Nawi Ads'],
            ],
            'packages' => $packages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tier' => ['required', Rule::in(['serious', 'serious_featured'])],
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $package = AdPackage::create($data);

        return response()->json(['success' => true, 'message' => 'Ad package created.', 'package' => $package]);
    }

    public function update(Request $request, AdPackage $adPackage): JsonResponse
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $adPackage->update($data);

        return response()->json(['success' => true, 'message' => 'Ad package updated.', 'package' => $adPackage]);
    }

    public function toggleActive(AdPackage $adPackage): JsonResponse
    {
        $adPackage->update(['is_active' => !$adPackage->is_active]);

        return response()->json([
            'success' => true,
            'message' => $adPackage->is_active ? 'Ad package activated.' : 'Ad package deactivated.',
        ]);
    }

    public function destroy(AdPackage $adPackage): JsonResponse
    {
        $adPackage->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Ad package deactivated.']);
    }
}
