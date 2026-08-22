<?php

namespace App\Http\Requests\Delivery\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class AcceptAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
