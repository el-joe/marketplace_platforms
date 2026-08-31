<?php

namespace App\Http\Controllers\Customer\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\TravelPackage;
use App\Enums\TravelPackageInquiryStatus;
use App\Enums\TravelPackageStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelPackageInquiryController extends Controller
{
    public function store(Request $request, $country, string $slug): JsonResponse
    {
        $package = TravelPackage::where('slug', $slug)
            ->where('status', TravelPackageStatus::Active)
            ->first();

        if (! $package) {
            return ApiResponse::error(__('common.exceptions.listing.travel_package_not_found_expired'), [], 404);
        }

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['required', 'email', 'max:255'],
            'travelers_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'message'         => ['required', 'string', 'max:1000'],
        ]);

        $package->inquiries()->create([
            'name'            => $data['name'],
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'],
            'travelers_count' => $data['travelers_count'] ?? null,
            'message'         => $data['message'],
            'status'          => TravelPackageInquiryStatus::New,
        ]);

        return ApiResponse::success(null, __('common.exceptions.listing.inquiry_submitted'), 201);
    }
}
