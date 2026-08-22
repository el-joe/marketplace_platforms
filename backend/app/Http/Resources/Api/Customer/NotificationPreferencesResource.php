<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            'marketing_preferences' => [
                'email' => $this->marketing_email_enabled,
                'sms' => $this->marketing_sms_enabled,
                'whatsapp' => $this->marketing_whatsapp_enabled,
            ],
        ];
    }
}
