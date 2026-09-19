<?php

namespace Tests\Unit;

use App\Enums\MarketerCommissionDiscountType as M;
use App\Enums\VendorCommissionDiscountType as V;
use App\Models\MarketerProfile;
use App\Models\Vendor;
use PHPUnit\Framework\TestCase;

class CommissionDiscountTest extends TestCase
{
    private function v(V $t, int $flat = 0, float $pct = 0): Vendor
    {
        $x = new Vendor();
        $x->commission_discount_type = $t;
        $x->commission_discount_flat = $flat;
        $x->commission_discount_percentage = $pct;
        return $x;
    }

    private function m(M $t, int $flat = 0, float $pct = 0): MarketerProfile
    {
        $x = new MarketerProfile();
        $x->commission_discount_type = $t;
        $x->commission_discount_flat = $flat;
        $x->commission_discount_percentage = $pct;
        return $x;
    }

    public function test_vendor_discounts(): void
    {
        $this->assertSame(1000, $this->v(V::None)->applyCommissionDiscount(1000));
        $this->assertSame(500, $this->v(V::Flat, 500)->applyCommissionDiscount(1000));
        $this->assertSame(0, $this->v(V::Flat, 5000)->applyCommissionDiscount(1000));
        $this->assertSame(900, $this->v(V::Percentage, 0, 10)->applyCommissionDiscount(1000));
        $this->assertSame(0, $this->v(V::Percentage, 0, 100)->applyCommissionDiscount(1000));
        $this->assertSame(7, $this->v(V::Percentage, 0, 33.3)->applyCommissionDiscount(10));
    }

    public function test_marketer_discounts(): void
    {
        $this->assertSame(1000, $this->m(M::None)->applyCommissionDiscount(1000));
        $this->assertSame(500, $this->m(M::Flat, 500)->applyCommissionDiscount(1000));
        $this->assertSame(0, $this->m(M::Flat, 5000)->applyCommissionDiscount(1000));
        $this->assertSame(900, $this->m(M::Percentage, 0, 10)->applyCommissionDiscount(1000));
    }
}
