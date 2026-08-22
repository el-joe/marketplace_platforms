<?php

namespace App\Http\Requests\Vendor\Classified;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassifiedListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classified_category_id' => 'required|exists:classified_categories,id',
            'country_id'             => 'required|exists:countries,id',
            'city_id'                => 'nullable|exists:cities,id',
            'listing_purpose'        => 'required|in:sale,rent',
            'title_en'               => 'required|string|max:255',
            'title_ar'               => 'required|string|max:255',
            'description_en'         => 'nullable|string',
            'description_ar'         => 'nullable|string',
            'price'            => 'required|integer|min:0',
            'currency'               => 'required|string|size:3',
            'price_negotiable'       => 'boolean',
            'attributes'             => 'nullable|array',
            'latitude'               => 'nullable|numeric|between:-90,90',
            'longitude'              => 'nullable|numeric|between:-180,180',
            'images'                 => 'required|array|min:1',
            'images.*'               => 'required|file|image|max:10240',
            'sketch_file'            => 'nullable|file|max:20480',
            'attachments'              => 'nullable|array',
            'attachments.*'            => 'file|max:20480',
            'vendor_listing_reference' => 'nullable|string|max:100',
        ];
    }
}
