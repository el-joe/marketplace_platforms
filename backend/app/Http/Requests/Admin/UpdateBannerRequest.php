<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerAudience;
use App\Enums\BannerDeviceTarget;
use App\Enums\BannerLinkType;
use App\Enums\BannerStatus;
use App\Http\Requests\Admin\Concerns\HasBannerMarketerTargeting;
use App\Http\Requests\Admin\Concerns\RequiresBannerDeviceImages;
use App\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBannerRequest extends FormRequest
{
    use HasBannerMarketerTargeting;
    use RequiresBannerDeviceImages;

    public function authorize(): bool
    {
        // Checked before validation so an unauthorised admin gets 403, not field errors.
        return (bool) $this->user('admin')?->hasPermissionTo('banners.edit');
    }

    public function rules(): array
    {
        return $this->marketerTargetingRules() + [
            'name' => ['required', 'string', 'max:150'],
            'placement_code' => ['required', 'string', 'max:100', 'exists:banner_placement_definitions,code'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
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

    /** @return list<callable> */
    public function after(): array
    {
        return $this->deviceImageChecks($this->route('banner') instanceof Banner ? $this->route('banner') : null);
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => __('admin.banners.ends_after_starts'),
            'placement_code.exists' => __('admin.banners.placement_invalid'),
            'marketer_ids.required' => __('admin.banners.marketers_required'),
        ];
    }
}
