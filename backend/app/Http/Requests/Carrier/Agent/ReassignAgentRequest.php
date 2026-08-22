<?php

namespace App\Http\Requests\Carrier\Agent;

use Illuminate\Foundation\Http\FormRequest;

class ReassignAgentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'new_delivery_agent_id' => ['required', 'string', 'exists:delivery_agents,id'],
        ];
    }
}
