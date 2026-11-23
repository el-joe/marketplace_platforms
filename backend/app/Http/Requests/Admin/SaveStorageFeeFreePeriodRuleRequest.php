<?php

namespace App\Http\Requests\Admin;

use App\Models\StorageFeeFreePeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create/update an FBN storage free-period rule (Admin\FbnController).
 *
 * Weight ranges are closed intervals in whole grams; "open_ended" must be
 * sent explicitly for a range without a maximum, so an empty or unreadable
 * max field can no longer silently become "and above" (which is what made
 * 0–999 look like 0–∞ and collide with an existing 1000 g+ rule).
 */
class SaveStorageFeeFreePeriodRuleRequest extends FormRequest
{
    public const EFFECTIVE_FROM_CREATION = 'from_creation';

    public const EFFECTIVE_FROM_DATE = 'from_date';

    public function authorize(): bool
    {
        return true; // FBN admin routes are guarded by auth.admin.
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['min_weight_grams', 'max_weight_grams', 'free_days'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(strtr($value, [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                    '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                    '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                    ',' => '', '٬' => '',
                ]));
                $value = $value === '' ? null : $value;
            }
            $normalized[$field] = $value;
        }

        $normalized['open_ended'] = $this->boolean('open_ended');
        if ($normalized['open_ended']) {
            $normalized['max_weight_grams'] = null;
        }

        $normalized['effective_mode'] = $this->input('effective_mode', self::EFFECTIVE_FROM_CREATION);

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'min_weight_grams' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'open_ended' => ['boolean'],
            'max_weight_grams' => ['nullable', 'required_if:open_ended,false', 'integer', 'gte:min_weight_grams', 'max:4294967295'],
            'free_days' => ['required', 'integer', 'min:0', 'max:65535'],
            'effective_mode' => ['required', Rule::in([self::EFFECTIVE_FROM_CREATION, self::EFFECTIVE_FROM_DATE])],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_weight_grams.required_if' => __('admin.fbn_section.max_weight_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_weight_grams' => __('admin.fbn_section.min_weight_grams'),
            'max_weight_grams' => __('admin.fbn_section.max_weight_grams'),
            'free_days' => __('admin.fbn_section.free_days'),
            'effective_mode' => __('admin.fbn_section.effective_from'),
            'effective_from' => __('admin.fbn_section.effective_from_date'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $current = $this->route('freePeriodRule');

                $conflict = StorageFeeFreePeriodRule::query()
                    ->overlappingWeightRange((int) $this->input('min_weight_grams'), $this->filled('max_weight_grams') ? (int) $this->input('max_weight_grams') : null)
                    ->when($current instanceof StorageFeeFreePeriodRule, fn ($q) => $q->whereKeyNot($current->getKey()))
                    ->orderBy('min_weight_grams')
                    ->first();

                if ($conflict !== null) {
                    $validator->errors()->add('min_weight_grams', __('admin.fbn_section.rule_overlap', [
                        'range' => $conflict->weightRangeLabel(),
                        'days' => $conflict->free_days,
                    ]));
                }
            },
        ];
    }

    /**
     * Rule attributes to persist. "From creation" pins effective_from to the
     * rule's creation day; "from date" uses the chosen date (empty = all storage).
     *
     * @return array{min_weight_grams: int, max_weight_grams: ?int, free_days: int, effective_from: ?string}
     */
    public function ruleAttributes(?StorageFeeFreePeriodRule $existing = null): array
    {
        $effectiveFrom = $this->input('effective_mode') === self::EFFECTIVE_FROM_CREATION
            ? admin_tz($existing?->created_at ?? now())->toDateString()
            : $this->input('effective_from');

        return [
            'min_weight_grams' => (int) $this->input('min_weight_grams'),
            'max_weight_grams' => $this->filled('max_weight_grams') ? (int) $this->input('max_weight_grams') : null,
            'free_days' => (int) $this->input('free_days'),
            'effective_from' => $effectiveFrom,
        ];
    }
}
