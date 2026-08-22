<?php

namespace App\Http\Requests\Carrier\Agent;

use App\Enums\DeliveryAgentVehicleType;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:delivery_agents,email'],
            'phone'         => ['required', 'string', 'max:30', 'unique:delivery_agents,phone'],
            'password'      => ['required', 'string', 'min:8'],
            'vehicle_type'  => ['required', Rule::enum(DeliveryAgentVehicleType::class)],
            'license_plate' => ['required', 'string', 'max:20'],
            'zone_id'       => ['nullable', 'string', 'exists:delivery_zones,id'],
            'country_id'    => ['required', 'string', 'exists:countries,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $zoneId    = $this->input('zone_id');
            $countryId = $this->input('country_id');

            if ($zoneId && $countryId) {
                $match = DeliveryZone::where('id', $zoneId)
                    ->where('country_id', $countryId)
                    ->exists();

                if (!$match) {
                    $v->errors()->add('zone_id', 'The selected zone does not belong to the specified country.');
                }
            }
        });
    }
}
