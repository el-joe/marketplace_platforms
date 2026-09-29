<?php

namespace App\Notifications;

use App\Models\Coupon;
use App\Models\VendorAdmin;

/**
 * Sent to a vendor/marketer when an admin targets them directly on a coupon
 * (the `coupon_vendors`/`coupon_marketers` eligibility pivots), so they have
 * visibility into coupons that apply to them even though they don't own or
 * manage the coupon itself.
 */
class CouponTargetedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Coupon $coupon) {}

    public function notificationType(): string
    {
        return 'coupon_targeted';
    }

    public function notificationData(object $notifiable): array
    {
        $isVendor = $notifiable instanceof VendorAdmin;

        return [
            'title' => __('admin.coupons_section.coupon_targeted_title'),
            'message' => __('admin.coupons_section.coupon_targeted_message', ['code' => $this->coupon->code]),
            'url' => $isVendor ? route('partner.coupon-participation.index') : route('marketer.coupon-participation.index'),
            'coupon_id' => $this->coupon->id,
            'coupon_code' => $this->coupon->code,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
