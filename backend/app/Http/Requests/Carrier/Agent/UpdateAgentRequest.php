<?php

namespace App\Http\Requests\Carrier\Agent;

use App\Enums\DeliveryAgentStatus;
use App\Enums\DeliveryAgentVehicleType;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicle_type'  => ['sometimes', Rule::enum(DeliveryAgentVehicleType::class)],
            'license_plate' => ['sometimes', 'string', 'max:20'],
            'zone_id'       => ['sometimes', 'nullable', 'string', 'exists:delivery_zones,id'],
            'is_available'  => ['sometimes', 'boolean'],
            // status toggling: supervisors can activate/deactivate but not suspend
            'status'        => ['sometimes', Rule::in([DeliveryAgentStatus::Active->value, DeliveryAgentStatus::Inactive->value])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $zoneId    = $this->input('zone_id');
            $countryId = $this->input('country_id')
                ?? DeliveryAgent::where('id', $this->route('id'))->value('country_id');

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
