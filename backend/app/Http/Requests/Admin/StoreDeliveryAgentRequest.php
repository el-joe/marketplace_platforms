<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryAgentType;
use App\Enums\DeliveryAgentVehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:delivery_agents,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:delivery_agents,phone'],
            'password' => ['required', 'string', 'min:8'],
            'country_id' => ['required', 'exists:countries,id'],
            'zone_id' => ['nullable', 'exists:delivery_zones,id'],
            'shipping_company_id' => ['nullable', 'exists:shipping_companies,id'],
            'agent_type' => ['required', Rule::enum(DeliveryAgentType::class)],
            'vehicle_type' => ['required', Rule::enum(DeliveryAgentVehicleType::class)],
            'national_id' => ['nullable', 'string', 'max:30'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'base_salary' => ['nullable', 'integer', 'min:0'],
            'per_delivery_fee' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
