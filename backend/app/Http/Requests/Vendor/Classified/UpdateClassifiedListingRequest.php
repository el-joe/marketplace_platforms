<?php

namespace App\Http\Requests\Vendor\Classified;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassifiedListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classified_category_id' => 'sometimes|exists:classified_categories,id',
            'country_id'             => 'sometimes|exists:countries,id',
            'city_id'                => 'nullable|exists:cities,id',
            'listing_purpose'        => 'sometimes|in:sale,rent',
            'title_en'               => 'sometimes|string|max:255',
            'title_ar'               => 'sometimes|string|max:255',
            'description_en'         => 'nullable|string',
            'description_ar'         => 'nullable|string',
            'price'            => 'sometimes|integer|min:0',
            'currency'               => 'sometimes|string|size:3',
            'price_negotiable'       => 'boolean',
            'attributes'             => 'nullable|array',
            'latitude'               => 'nullable|numeric|between:-90,90',
            'longitude'              => 'nullable|numeric|between:-180,180',
            'images'                 => 'sometimes|array|min:1',
            'images.*'               => 'file|image|max:10240',
            'sketch_file'            => 'nullable|file|max:20480',
            'attachments'            => 'nullable|array',
            'attachments.*'          => 'file|max:20480',
        ];
    }
}
