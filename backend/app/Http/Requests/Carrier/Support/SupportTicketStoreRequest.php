<?php

namespace App\Http\Requests\Carrier\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'      => ['required', 'string', 'in:order_issue,payment_issue,account,technical,product_inquiry,policy,payout,catalog,other'],
            'subject'       => ['required', 'string', 'max:255'],
            'message'       => ['required', 'string', 'max:10000'],
            'priority'      => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'agent_id'      => ['nullable', 'uuid', 'exists:admins,id'],
            'assignment_id' => ['nullable', 'uuid', 'exists:delivery_assignments,id'],
            'attachment'    => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ];
    }
}
