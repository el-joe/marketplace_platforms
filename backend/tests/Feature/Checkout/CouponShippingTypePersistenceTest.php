<?php

namespace Tests\Feature\Checkout;

use App\Models\Admin;
use App\Models\Coupon;
use App\Models\VendorAdmin;
use App\Services\Admin\CouponService as AdminCouponService;
use App\Services\Vendor\CouponService as VendorCouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** shipping_type_restriction round-trips through the admin and vendor coupon services. */
class CouponShippingTypePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function adminPayload(array $o = []): array
    {
        return array_merge([
            'code' => 'ADM' . random_int(1000, 9999), 'name' => 'Admin coupon', 'type' => 'percentage', 'value' => 10,
            'scope' => 'platform', 'usage_limit_per_customer' => 1, 'customer_eligibility' => 'all',
            'valid_from' => now()->toDateTimeString(), 'valid_until' => now()->addDay()->toDateTimeString(),
            'funded_by' => 'platform',
        ], $o);
    }

    private function vendorPayload(array $o = []): array
    {
        return array_merge([
            'code' => 'VEN' . random_int(1000, 9999), 'name' => 'Vendor coupon', 'type' => 'percentage', 'value' => 10,
            'scope' => 'vendor', 'usage_limit_per_customer' => 1,
            'valid_from' => now()->toDateTimeString(), 'valid_until' => now()->addDay()->toDateTimeString(),
        ], $o);
    }

    public function test_admin_service_persists_on_create_and_update(): void
    {
        $svc = app(AdminCouponService::class);
        $admin = Admin::factory()->create();

        $coupon = $svc->create($this->adminPayload(['shipping_type_restriction' => 'fbn']), $admin);
        $this->assertSame('fbn', $coupon->fresh()->shipping_type_restriction->value);

        $payload = $this->adminPayload(['code' => $coupon->code, 'shipping_type_restriction' => 'fbm']);
        $svc->update($coupon, $payload);
        $this->assertSame('fbm', $coupon->fresh()->shipping_type_restriction->value);

        $svc->update($coupon->fresh(), $this->adminPayload(['code' => $coupon->code, 'shipping_type_restriction' => null]));
        $this->assertNull($coupon->fresh()->shipping_type_restriction);
    }

    public function test_vendor_service_persists_on_create_and_update(): void
    {
        $s = MarketplaceScenario::make()->build();
        $actor = new VendorAdmin();
        $actor->id = $s->vendor->id;
        $svc = app(VendorCouponService::class);

        $coupon = $svc->create($s->vendor, $actor, $this->vendorPayload(['shipping_type_restriction' => 'fbp']));
        $this->assertSame('fbp', Coupon::find($coupon->id)->shipping_type_restriction->value);

        $svc->update($coupon, $s->vendor, $this->vendorPayload(['code' => $coupon->code, 'shipping_type_restriction' => 'all']));
        $this->assertSame('all', Coupon::find($coupon->id)->shipping_type_restriction->value);
    }

    public function test_invalid_value_rejected_by_all_requests(): void
    {
        foreach ([
            \App\Http\Requests\Admin\StoreCouponRequest::class,
            \App\Http\Requests\Admin\UpdateCouponRequest::class,
            \App\Http\Requests\Vendor\StoreCouponRequest::class,
            \App\Http\Requests\Vendor\UpdateCouponRequest::class,
        ] as $cls) {
            $rules = (new $cls())->rules()['shipping_type_restriction'];
            $this->assertTrue(\Validator::make(['shipping_type_restriction' => 'bogus'], ['shipping_type_restriction' => $rules])->fails(), $cls);
            $this->assertFalse(\Validator::make(['shipping_type_restriction' => 'fbn'], ['shipping_type_restriction' => $rules])->fails(), $cls);
        }
    }
}
