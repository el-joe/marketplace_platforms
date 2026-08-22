<?php

namespace App\Http\Requests\Api\TravelAgencyPortal\Package;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'destination_travel_country_id' => ['required', 'uuid', 'exists:travel_countries,id'],
            'destination_travel_city_id' => ['nullable', 'uuid', 'exists:travel_cities,id'],
            'price' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'pricing_tiers_enabled' => ['nullable', 'boolean'],
            'show_pricing_tiers_to_customer' => ['nullable', 'boolean'],
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.travelers_count' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required_with:price_tiers', 'integer', 'min:1'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'duration_nights' => ['required', 'integer', 'min:0'],
            'departure_date' => ['required', 'date', 'after:today'],
            'return_date' => ['required', 'date', 'after:departure_date'],
            'available_seats' => ['nullable', 'integer', 'min:1'],
            'inclusion_ids' => ['nullable', 'array'],
            'inclusion_ids.*' => ['uuid', 'exists:travel_inclusions,id'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
            'contract_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
