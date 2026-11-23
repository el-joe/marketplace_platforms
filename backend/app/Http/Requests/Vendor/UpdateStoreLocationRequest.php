<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Store location (business address pin) of a vendor, used as the distance-pricing origin when no warehouse
 * with coordinates fulfils the line (client item #19). Shared by the partner panel, the admin vendor page and
 * the vendor API; who may call it is decided by each route's permission middleware.
 */
class UpdateStoreLocationRequest extends FormRequest
{
    /** Arabic-Indic / Persian digits and the Arabic decimal separator, as typed on an Arabic keyboard. */
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٫' => '.', '٬' => '', '−' => '-',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalize = function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }
            $value = trim(strtr($value, self::DIGITS));

            return $value === '' ? null : $value;
        };

        $this->merge([
            'store_address' => is_string($this->input('store_address')) ? (trim($this->input('store_address')) ?: null) : $this->input('store_address'),
            'latitude' => $normalize($this->input('latitude')),
            'longitude' => $normalize($this->input('longitude')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'store_address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // 0,0 is the default of some legacy forms; distance pricing ignores it, so never store it as a real pin.
            if ($this->filled('latitude') && (float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', __('store_location.validation.zero_point'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_address' => __('store_location.address'),
            'latitude' => __('store_location.latitude'),
            'longitude' => __('store_location.longitude'),
        ];
    }
}
