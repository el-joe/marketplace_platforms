<?php

namespace Tests\Feature\Commission;

use App\Models\FlashSale;
use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCampaignConversion;
use Tests\TestCase;

class FlashSaleBonusModeTest extends TestCase
{
    private function inv(array $a): FlashSaleMarketerInvitation
    {
        return new FlashSaleMarketerInvitation($a);
    }

    public function test_both_mode_is_percent_plus_flat_times_qty(): void
    {
        $i = $this->inv(['extra_commission_rate' => 2, 'extra_commission_mode' => 'both', 'extra_commission_flat_amount' => 3]);
        $this->assertSame(26, $i->calculateBonus(1000, 2));
        $this->assertSame('2% + 3 AED', $i->commissionLabel('AED'));
    }

    public function test_percentage_only_unchanged(): void
    {
        $i = $this->inv(['extra_commission_rate' => 2.5, 'extra_commission_mode' => 'percentage', 'extra_commission_flat_amount' => 9]);
        $this->assertSame(25, $i->calculateBonus(1000, 2));
        $this->assertSame('2.5%', $i->commissionLabel('AED'));
    }

    public function test_default_mode_null_behaves_as_percentage(): void
    {
        $this->assertSame(20, $this->inv(['extra_commission_rate' => 2])->calculateBonus(1000, 2));
    }

    public function test_fixed_only(): void
    {
        $i = $this->inv(['extra_commission_rate' => 50, 'extra_commission_mode' => 'fixed', 'extra_commission_flat_amount' => 3]);
        $this->assertSame(6, $i->calculateBonus(1000, 2));
        $this->assertSame('3 AED', $i->commissionLabel('AED'));
    }
}
