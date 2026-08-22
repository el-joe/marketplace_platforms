<?php

namespace App\Http\Requests\Customer\Dispute;

use App\Enums\DisputeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisputeStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason'      => ['required', 'string', Rule::enum(DisputeReason::class)],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
