<?php

namespace App\Http\Requests\Admin;

use App\Services\Shipping\DistancePricing\DistanceFeeCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Distance pricing rule for one zone + method (client item #19). Structural tier errors (overlap,
 * inverted band, misplaced open-ended band) block the save; gaps are returned as warnings.
 */
class UpsertShippingDistancePricingRequest extends FormRequest
{
    /** Arabic-Indic / Persian digits and the Arabic decimal separator, as typed on an Arabic keyboard. */
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٫' => '.', '٬' => '',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalize = fn ($value) => is_string($value) ? trim(strtr($value, self::DIGITS)) : $value;
        $nullIfBlank = fn ($value) => $value === '' ? null : $value;

        $tiers = collect($this->input('tiers', []))
            ->map(fn ($tier) => [
                'from_km' => $nullIfBlank($normalize($tier['from_km'] ?? null)),
                'to_km' => $nullIfBlank($normalize($tier['to_km'] ?? null)),
                'price' => $nullIfBlank($normalize($tier['price'] ?? null)),
            ])
            ->values()
            ->all();

        $this->merge([
            'free_radius_km' => $nullIfBlank($normalize($this->input('free_radius_km'))),
            'max_service_radius_km' => $nullIfBlank($normalize($this->input('max_service_radius_km'))),
            'is_active' => $this->boolean('is_active'),
            'tiers' => $tiers,
            'category_ids' => array_values(array_filter((array) $this->input('category_ids', []))),
        ]);
    }

    public function rules(): array
    {
        return [
            'free_radius_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'max_service_radius_km' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'is_active' => ['boolean'],
            'tiers' => ['array', 'max:50'],
            'tiers.*.from_km' => ['required', 'numeric', 'min:0', 'max:9999'],
            'tiers.*.to_km' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'tiers.*.price' => ['required', 'numeric', 'min:0'],
            'category_ids' => ['array'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'free_radius_km' => __('distance_delivery.admin.free_radius_km'),
            'max_service_radius_km' => __('distance_delivery.admin.max_service_radius_km'),
            'tiers.*.from_km' => __('distance_delivery.admin.from_km'),
            'tiers.*.to_km' => __('distance_delivery.admin.to_km'),
            'tiers.*.price' => __('distance_delivery.admin.price'),
            'category_ids' => __('distance_delivery.admin.categories'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('tiers') === [] && $this->input('free_radius_km') === null && $this->input('max_service_radius_km') === null) {
                    $validator->errors()->add('tiers', __('distance_delivery.admin.errors.tier_required'));

                    return;
                }

                foreach ($this->analysis()['errors'] as $error) {
                    $validator->errors()->add('tiers', __('distance_delivery.admin.errors.'.$error['key'], $error['params']));
                }
            },
        ];
    }

    /**
     * @return array{errors: list<array{key: string, params: array<string, mixed>}>, warnings: list<array{key: string, params: array<string, mixed>}>}
     */
    public function analysis(): array
    {
        $toFloat = fn ($value) => $value === null ? null : (float) $value;

        return DistanceFeeCalculator::analyseTiers(
            $this->input('tiers', []),
            $toFloat($this->input('free_radius_km')),
            $toFloat($this->input('max_service_radius_km')),
        );
    }
}
