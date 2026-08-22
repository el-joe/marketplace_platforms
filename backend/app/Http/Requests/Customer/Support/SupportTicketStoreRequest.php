<?php

namespace App\Http\Requests\Customer\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'category'     => ['required', 'string', 'in:order_issue,payment_issue,account,technical,product_inquiry,policy,payout,catalog,other'],
            'subject'      => ['required', 'string', 'max:255'],
            'message'      => ['required', 'string', 'max:10000'],
            'priority'     => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'order_number' => ['nullable', 'string', 'max:30'],
            'attachment'   => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ];
    }
}
