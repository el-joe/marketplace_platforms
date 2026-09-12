<?php

namespace App\Http\Requests\Customer;

use App\Enums\DeliveryInstruction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id'                 => ['required', 'integer', 'exists:addresses,id'],
            'receiver_id'                => ['nullable', 'uuid', 'exists:customer_receivers,id'],
            'country_payment_gateway_id' => ['required', 'uuid', 'exists:country_payment_gateways,id'],
            'coupon_code'                => ['nullable', 'string', 'max:50'],
            'wallet_amount_to_use'       => ['nullable', 'integer', 'min:1'],
            'wallet_amount_used'         => ['nullable', 'integer', 'min:0'],
            'customer_notes'             => ['nullable', 'string', 'max:500'],
            'delivery_instruction'       => ['nullable', Rule::enum(DeliveryInstruction::class)],
            'idempotency_key'            => ['required', 'string', 'max:100'],
            'warranty_selections'        => ['nullable', 'array'],
            'warranty_selections.*.listing_id'      => ['required_with:warranty_selections', 'uuid'],
            'warranty_selections.*.warranty_plan_id' => ['required_with:warranty_selections', 'uuid'],
            'loyalty_points_to_use'      => ['nullable', 'numeric', 'min:1'],
            'contract_acceptance_id'     => ['nullable', 'uuid', 'exists:marketer_contract_acceptances,id'],
            'custom_inputs'               => ['nullable', 'array'],
            'custom_inputs.*.listing_id'  => ['required_with:custom_inputs', 'uuid'],
            'custom_inputs.*.order_note'  => ['nullable', 'string', 'max:500'],
            'custom_inputs.*.fields'      => ['nullable', 'array'],
            'custom_inputs.*.fields.*.field_id' => ['required_with:custom_inputs.*.fields', 'uuid'],
            'custom_inputs.*.fields.*.value'    => ['nullable', 'string', 'max:1000'],
            'custom_inputs.*.addon_options'      => ['nullable', 'array'],
            'custom_inputs.*.addon_options.*'    => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'wallet_amount_used.integer' => 'Wallet amount must be a whole number.',
            'wallet_amount_used.min'     => 'Wallet amount cannot be negative.',
            'loyalty_points_to_use.numeric' => 'Loyalty points must be a number.',
            'loyalty_points_to_use.min'     => 'Loyalty points to use must be at least 1.',
        ];
    }
}
