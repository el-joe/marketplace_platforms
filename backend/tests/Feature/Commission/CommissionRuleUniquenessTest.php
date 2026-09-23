<?php

namespace Tests\Feature\Commission;

use App\Models\Category;
use App\Models\MarketerCommissionRule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRuleUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_scope_default_cannot_be_duplicated(): void
    {
        $attrs = ['marketer_id' => null, 'scope' => 'open_market', 'commission_rate' => 5];
        MarketerCommissionRule::create($attrs);

        $this->expectException(QueryException::class);
        MarketerCommissionRule::create($attrs);
    }

    public function test_rule_key_differs_per_scope_and_category(): void
    {
        $a = MarketerCommissionRule::makeRuleKey('m1', 'products', Category::class, 'c1');
        $b = MarketerCommissionRule::makeRuleKey('m1', 'products', Category::class, 'c2');
        $c = MarketerCommissionRule::makeRuleKey('m1', 'products', null, null);
        $d = MarketerCommissionRule::makeRuleKey('m1', 'open_market', null, null);

        $this->assertCount(4, array_unique([$a, $b, $c, $d]));
    }
}
