<?php

namespace App\Http\Requests\Customer\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status'    => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to'   => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page'      => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
