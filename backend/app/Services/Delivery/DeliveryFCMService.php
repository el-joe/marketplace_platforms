<?php

namespace App\Services\Delivery;

use App\Models\DeliveryAgent;
use App\Models\DeviceToken;
use App\Services\Vendor\VendorFCMService;

/**
 * Sends push notifications to delivery agent mobile devices via FCM.
 *
 * Mirrors App\Services\Carrier\CarrierFCMService / Marketer\MarketerFCMService —
 * the actual FCM HTTP v1 delivery (including the Google OAuth2 token exchange)
 * is delegated to VendorFCMService::sendToToken(), which is already generic
 * over any token.
 */
class DeliveryFCMService
{
    public function __construct(private readonly VendorFCMService $fcmService) {}

    public function sendToAgent(string $agentId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('tokenable_type', DeliveryAgent::class)
            ->where('tokenable_id', $agentId)
            ->where('is_active', 1)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->fcmService->sendToToken($token, $title, $body, $data);
        }
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        $this->fcmService->sendToToken($token, $title, $body, $data);
    }
}
