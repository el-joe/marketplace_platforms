<?php

namespace Tests\Feature\Commission;

use App\Http\Controllers\Api\Marketer\CommissionRuleController;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketerCommissionRulesApiTest extends TestCase
{
    use RefreshDatabase;

    private function marketer(): Marketer
    {
        return Marketer::create([
            'name' => 'M', 'email' => 'm-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);
    }

    public function test_rules_for_returns_only_own_rules(): void
    {
        $a = $this->marketer();
        $b = $this->marketer();
        MarketerCommissionRule::create(['marketer_id' => $a->id, 'scope' => 'products', 'commission_mode' => 'both', 'commission_rate' => 10, 'commission_flat_amount' => 5]);
        MarketerCommissionRule::create(['marketer_id' => $b->id, 'scope' => 'products', 'commission_mode' => 'fixed', 'commission_flat_amount' => 9]);

        $rows = CommissionRuleController::rulesFor($a);
        $this->assertCount(1, $rows);
        $this->assertSame('both', $rows[0]['commission_mode']);
        $this->assertNull($rows[0]['category']);
    }
}
