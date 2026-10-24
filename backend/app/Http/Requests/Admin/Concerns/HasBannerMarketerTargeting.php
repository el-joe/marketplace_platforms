<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\BannerGenderScope;
use App\Models\Banner;
use Illuminate\Validation\Rule;

trait HasBannerMarketerTargeting
{
    protected function isMarketerPlacement(): bool
    {
        return in_array($this->input('placement_code'), Banner::MARKETER_PLACEMENTS, true);
    }

    /**
     * Targeting rules: only enforced for marketer placements; ignored (and cleared by the controller) otherwise.
     *
     * @return array<string, mixed>
     */
    protected function marketerTargetingRules(): array
    {
        $needsMarketers = fn (): bool => $this->isMarketerPlacement() && ! $this->boolean('is_global');

        return [
            'is_global' => ['nullable', 'boolean'],
            'gender_scope' => ['nullable', Rule::enum(BannerGenderScope::class)],
            'marketer_ids' => [Rule::requiredIf($needsMarketers), 'nullable', 'array'],
            'marketer_ids.*' => [Rule::when($needsMarketers, ['uuid', 'exists:marketers,id'], ['nullable'])],
        ];
    }
}
