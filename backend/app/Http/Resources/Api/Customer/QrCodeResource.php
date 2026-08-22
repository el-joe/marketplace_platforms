<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'qr_url' => Storage::disk('public')->url($this->qr_code_path),
            'referral_code' => $this->referral_code,
            'referral_link' => rtrim(config('app.url'), '/').'/r/'.$this->referral_code,
        ];
    }
}
