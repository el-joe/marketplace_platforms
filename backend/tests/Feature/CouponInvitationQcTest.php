<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CouponParticipationInvitationController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CouponInvitationQcTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lang_keys_exist_in_both_locales(): void
    {
        foreach (['en', 'ar'] as $l) {
            app()->setLocale($l);
            $this->assertNotSame('partner.product_vendors_only', __('partner.product_vendors_only'));
            $this->assertNotSame('admin.coupon_participation_section.coupon_value_zero', __('admin.coupon_participation_section.coupon_value_zero'));
            $this->assertNotSame('partner.cp_status_paid', __('partner.cp_status_paid'));
        }
        $this->assertTrue(method_exists(CouponParticipationInvitationController::class, 'store'));
    }
}
