<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Decrypt to get last 4 digits only — the full number is never exposed.
        $masked = $this->maskedAccountNumber();

        return [
            'id'                    => $this->id,
            'account_holder_name'   => $this->account_holder_name,
            'bank_name'             => $this->bank_name,
            'branch'                => $this->branch,
            'iban'                  => $this->iban,
            'account_number_masked' => $masked,
            'swift_code'            => $this->swift_code,
            'currency'              => $this->currency,
            'is_primary'            => (bool) $this->is_primary,
            'verification_status'   => $this->verification_status?->value,
            'verified_at'           => $this->verified_at?->toISOString(),
            'created_at'            => $this->created_at?->toISOString(),
        ];
    }

    private function maskedAccountNumber(): string
    {
        try {
            $plain = decrypt($this->account_number_encrypted);
            $last4 = substr((string) $plain, -4);

            return '••••' . $last4;
        } catch (\Throwable) {
            return '••••';
        }
    }
}
