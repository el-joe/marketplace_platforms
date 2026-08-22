<?php

namespace App\Http\Requests\Carrier\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServedAreasRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'country_ids'   => ['present', 'array'],
            'country_ids.*' => ['string', 'exists:countries,id'],
            'city_ids'      => ['present', 'array'],
            'city_ids.*'    => ['string', 'exists:cities,id'],
        ];
    }
}
