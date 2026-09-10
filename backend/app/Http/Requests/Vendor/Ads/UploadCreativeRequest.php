<?php

namespace App\Http\Requests\Vendor\Ads;

use Illuminate\Foundation\Http\FormRequest;

class UploadCreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desktop_en' => ['required', 'image'],
            'desktop_ar' => ['nullable', 'image'],
            'mobile_en' => ['required', 'image'],
            'mobile_ar' => ['nullable', 'image'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'cta_label_en' => ['nullable', 'string', 'max:60'],
            'cta_label_ar' => ['nullable', 'string', 'max:60'],
            'destination_type' => ['required', 'in:listing,classified_listing,store,brand,category'],
            'destination_reference_id' => ['nullable', 'string'],
        ];
    }
}
