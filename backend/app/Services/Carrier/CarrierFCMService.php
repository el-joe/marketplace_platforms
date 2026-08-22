<?php

namespace App\Services\Carrier;

use App\Models\DeviceToken;
use App\Models\ShippingCompanySupervisor;
use App\Services\Vendor\VendorFCMService;

/**
 * Sends push notifications to carrier supervisor mobile devices via FCM.
 *
 * Mirrors App\Services\Marketer\MarketerFCMService — the actual FCM HTTP v1
 * delivery (including the Google OAuth2 token exchange) is delegated to
 * VendorFCMService::sendToToken(), which is already generic over any token.
 */
class CarrierFCMService
{
    public function __construct(private readonly VendorFCMService $fcmService) {}

    public function sendToSupervisor(string $supervisorId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('tokenable_type', ShippingCompanySupervisor::class)
            ->where('tokenable_id', $supervisorId)
            ->where('is_active', 1)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->fcmService->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Notify all active supervisors belonging to a shipping company.
     */
    public function sendToCompanySupervisors(string $shippingCompanyId, string $title, string $body, array $data = []): void
    {
        $supervisorIds = ShippingCompanySupervisor::where('shipping_company_id', $shippingCompanyId)
            ->where('is_active', true)
            ->pluck('id');

        $tokens = DeviceToken::whereIn('tokenable_id', $supervisorIds)
            ->where('tokenable_type', ShippingCompanySupervisor::class)
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
