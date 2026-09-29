<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BookableUnit;
use App\Models\TravelPackage;
use Illuminate\Http\JsonResponse;

class PackageBookableUnitsController extends Controller
{
    /**
     * GET /api/v1/packages/{packageId}/units
     * Returns the active bookable units for a published travel package.
     */
    public function __invoke(string $packageId): JsonResponse
    {
        $package = TravelPackage::where('id', $packageId)
            ->where('status', TravelPackageStatus::Active)
            ->first();

        if (! $package) {
            return ApiResponse::error('Package not found.', [], 404);
        }

        $units = BookableUnit::where('travel_package_id', $package->id)
            ->where('status', 'active')
            ->with('photos')
            ->get()
            ->map(fn (BookableUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'name_ar' => $unit->name_ar,
                'type' => $unit->type,
                'capacity' => $unit->capacity,
                'description' => $unit->description,
                'primary_photo_url' => $unit->primary_photo_url,
            ]);

        return ApiResponse::success([
            'package_id' => $package->id,
            'package_title' => $package->title_ar ?: $package->title_en,
            'units' => $units,
        ]);
    }
}
