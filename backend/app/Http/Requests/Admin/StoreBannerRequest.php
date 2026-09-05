<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerAudience;
use App\Enums\BannerDeviceTarget;
use App\Enums\BannerLinkType;
use App\Enums\BannerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'placement_code' => ['required', 'string', 'max:100', 'exists:banner_placement_definitions,code'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:500'],
            'subtitle_ar' => ['nullable', 'string', 'max:500'],
            'cta_label_en' => ['nullable', 'string', 'max:100'],
            'cta_label_ar' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'link_type' => ['nullable', Rule::enum(BannerLinkType::class)],
            'link_reference_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::enum(BannerStatus::class)->only([
                BannerStatus::Active, BannerStatus::Inactive, BannerStatus::Scheduled,
            ])],
            'device_target' => ['required', Rule::enum(BannerDeviceTarget::class)],
            'audience' => ['required', Rule::enum(BannerAudience::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
            'desktop_image_en' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120'],
            'desktop_image_ar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120'],
            'mobile_image_en' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120'],
            'mobile_image_ar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'End date must be after start date.',
            'placement_code.exists' => 'Please select a valid placement.',
        ];
    }
}
