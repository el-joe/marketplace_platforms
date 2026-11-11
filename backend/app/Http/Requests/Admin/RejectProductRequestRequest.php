<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin rejecting a vendor/marketer product request (Admin\ProductRequestController::reject).
 */
class RejectProductRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled at route level (admin.permission:product_requests.review)
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rejection_reason' => __('admin.product_requests.rejection_reason'),
        ];
    }
}
