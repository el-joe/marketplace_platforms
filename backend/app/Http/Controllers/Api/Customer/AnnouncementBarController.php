<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AnnouncementBar;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnnouncementBarController extends Controller
{
    /**
     * The active bar for a country, cached separately from the app_config payload so
     * this endpoint can be polled on its own. Busted alongside app_config_{countryId}
     * in Admin\AnnouncementBarController::bustCache().
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Country $country */
        $country = $request->attributes->get('country');

        $shaped = Cache::remember("announcement_bar_active_{$country->id}", 300, function () use ($country) {
            $bar = AnnouncementBar::getActive($country->id);

            if (!$bar) {
                return null;
            }

            // Shaped inside the closure so only a plain array is cached, never an
            // Eloquent model (which deserializes as __PHP_Incomplete_Class on the
            // database cache driver and crashes on the second request).
            return $this->shape($bar);
        });

        if (!$shaped) {
            return ApiResponse::success(['data' => []]);
        }

        return ApiResponse::success(['data' => [$shaped]]);
    }

    private function shape(AnnouncementBar $bar): array
    {
        return [
            'id' => $bar->id,
            'image_url' => $bar->image_url,
            'cta_url' => $bar->cta_url,
        ];
    }
}
