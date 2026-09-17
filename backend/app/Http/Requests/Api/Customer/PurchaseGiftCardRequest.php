<?php

namespace App\Http\Requests\Api\Customer;

use App\Models\CountryPaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PurchaseGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gift_card_batch_id' => ['required', 'uuid', 'exists:gift_card_batches,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'country_payment_gateway_id' => ['required', 'uuid', 'exists:country_payment_gateways,id'],
            'recipient_email' => ['nullable', 'email'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'gift_message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Gift cards are digital and delivered by email — Cash on Delivery has no
     * physical delivery to attach a COD payment to, so it's rejected here even
     * though the frontend selector also filters it out of the options list.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $gatewayId = $this->input('country_payment_gateway_id');

            if (! $gatewayId) {
                return;
            }

            $isCod = CountryPaymentGateway::where('id', $gatewayId)
                ->whereHas('gateway', fn ($q) => $q->where('code', 'cod'))
                ->exists();

            if ($isCod) {
                $validator->errors()->add(
                    'country_payment_gateway_id',
                    'Cash on Delivery is not available for gift card purchases.',
                );
            }
        });
    }
}
