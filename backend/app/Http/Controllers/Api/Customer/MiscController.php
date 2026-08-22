<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\City;
use App\Models\Country;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function countries(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->where('is_launched', true)
            ->orderBy('name_en')
            ->get()
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'iso_code_2' => $country->iso_code_2,
                'iso_code_3' => $country->iso_code_3,
                'name' => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en,
                'flag_emoji' => $country->flag_emoji,
                'phone_prefix' => $country->phone_prefix,
                'currency_code' => $country->currency_code,
                'site_code' => $country->site_code,
                'cod_available' => $country->cod_available,
            ]);

        return ApiResponse::success($countries);
    }

    public function cities(Request $request, string $country): JsonResponse
    {
        $countryModel = Country::query()
            ->where('is_active', true)
            ->where('is_launched', true)
            ->where(function ($query) use ($country) {
                $query->where('id', $country)
                    ->orWhere('site_code', $country)
                    ->orWhere('iso_code_2', $country);
            })
            ->first();

        if (! $countryModel) {
            return ApiResponse::error(__('customer_api.misc.country_not_found'), [], 404);
        }

        $cities = City::query()
            ->forCountry($countryModel->id)
            ->where('is_active', true)
            ->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en')
            ->get()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'cod_available' => $city->cod_available,
            ]);

        return ApiResponse::success($cities);
    }

    /**
     * List all active shipping methods (flat, country-agnostic).
     *
     * GET /v1/shipping-methods
     * Auth: required (auth:customer)
     *
     * Returns the platform's active shipping methods with their delivery day
     * estimates. Does NOT calculate fees (no address/cart context available
     * at this endpoint). Use the checkout shipping-methods endpoint for
     * fee-calculated options once an address is selected.
     */
    public function shippingMethods(Request $request): JsonResponse
    {
        $methods = ShippingMethod::query()
            ->where('is_active', 1)
            ->orderBy('display_priority')
            ->get()
            ->map(fn (ShippingMethod $method) => [
                'id'                   => $method->id,
                'code'                 => $method->code,
                'name'                 => $method->name,
                'badge_label_en'       => $method->badge_label_en,
                'badge_label_ar'       => $method->badge_label_ar,
                'badge_color_hex'      => $method->badge_color_hex,
                'badge_text_color_hex' => $method->badge_text_color_hex,
                'delivery_days_min'    => $method->min_delivery_days,
                'delivery_days_max'    => $method->max_delivery_days,
            ]);

        return ApiResponse::success($methods, __('common.exceptions.checkout.shipping_methods_retrieved'));
    }
}
