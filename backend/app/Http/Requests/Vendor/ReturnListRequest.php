<?php

namespace App\Http\Requests\Vendor;

use App\Enums\ReturnRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'    => ['nullable', Rule::enum(ReturnRequestStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }
}
