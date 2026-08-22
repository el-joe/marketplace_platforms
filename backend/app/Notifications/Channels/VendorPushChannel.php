<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Services\Vendor\VendorFCMService;
use Illuminate\Notifications\Notification;

/**
 * Generic push channel: dispatches to any notifiable model's device token via
 * DeviceToken's polymorphic tokenable_type/tokenable_id lookup, regardless of
 * whether the notifiable is a VendorAdmin, Marketer, or any other future model.
 */
class VendorPushChannel
{
    public function __construct(private readonly VendorFCMService $fcmService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        $token = DeviceToken::where('tokenable_type', get_class($notifiable))
            ->where('tokenable_id', $notifiable->getKey())
            ->where('is_active', 1)
            ->value('token');

        if (!$token) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        $this->fcmService->sendToToken(
            $token,
            $payload['title'],
            $payload['body'],
            $payload['data'] ?? [],
        );
    }
}
