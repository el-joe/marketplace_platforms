<?php

namespace App\Http\Requests\Vendor\Classified;

use App\Enums\ClassifiedInquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInquiryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([ClassifiedInquiryStatus::Contacted->value, ClassifiedInquiryStatus::Closed->value]),
            ],
        ];
    }
}
