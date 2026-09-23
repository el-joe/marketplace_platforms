<?php

namespace Tests\Feature\Commission;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use App\Models\TravelCategory;
use App\Services\CommissionRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    private function marketer(): Marketer
    {
        return Marketer::create([
            'name' => 'M', 'email' => 'm-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);
    }

    private function rule(array $a): MarketerCommissionRule
    {
        return MarketerCommissionRule::create($a + ['commission_mode' => 'percentage', 'commission_rate' => 0]);
    }

    public function test_amounts(): void
    {
        $both = new MarketerCommissionRule(['commission_mode' => 'both', 'commission_rate' => 10, 'commission_flat_amount' => 5]);
        $this->assertSame(15, $both->resolveAmount(100));
        $this->assertSame(10 + 15, $both->resolveAmount(100, 3));
        $fixed = new MarketerCommissionRule(['commission_mode' => 'fixed', 'commission_rate' => 50, 'commission_flat_amount' => 7]);
        $this->assertSame(21, $fixed->resolveAmount(1000, 3));
        $pct = new MarketerCommissionRule(['commission_mode' => 'percentage', 'commission_rate' => 8, 'commission_flat_amount' => 9]);
        $this->assertSame(8, $pct->resolveAmount(100, 5));
    }

    public function test_precedence_and_parent_chain(): void
    {
        $m = $this->marketer();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $r = app(CommissionRuleResolver::class);

        $this->assertNull($r->resolve($m->id, 'products', $child));
        $plat = $this->rule(['scope' => 'products', 'commission_rate' => 1]);
        $this->assertNull($r->resolve($m->id, 'products', $child));
        $this->assertSame($plat->id, $r->resolve($m->id, 'products', $child, true)->id);

        $def = $this->rule(['marketer_id' => $m->id, 'scope' => 'products', 'commission_rate' => 2]);
        $this->assertSame($def->id, $r->resolve($m->id, 'products', $child)->id);

        $p = $this->rule(['marketer_id' => $m->id, 'scope' => 'products', 'category_type' => Category::class, 'category_id' => $parent->id, 'commission_rate' => 3]);
        $this->assertSame($p->id, $r->resolve($m->id, 'products', $child)->id);

        $c = $this->rule(['marketer_id' => $m->id, 'scope' => 'products', 'category_type' => Category::class, 'category_id' => $child->id, 'commission_rate' => 4]);
        $this->assertSame($c->id, $r->resolve($m->id, 'products', $child)->id);
        $this->assertSame(4, $r->calculate($m->id, 'products', $child->id, 100));
    }

    public function test_scopes_are_isolated_and_morph(): void
    {
        $m = $this->marketer();
        $cc = ClassifiedCategory::create(['name_en' => 'c', 'name_ar' => 'c', 'slug' => 'c-'.uniqid()]);
        $rule = $this->rule(['marketer_id' => $m->id, 'scope' => 'open_market', 'category_type' => ClassifiedCategory::class, 'category_id' => $cc->id, 'commission_rate' => 6]);
        $r = app(CommissionRuleResolver::class);
        $this->assertSame($rule->id, $r->resolve($m->id, 'open_market', $cc)->id);
        $this->assertNull($r->resolve($m->id, 'products', $cc->id));
        $this->assertInstanceOf(ClassifiedCategory::class, $rule->fresh()->category);
        $this->assertSame(TravelCategory::class, MarketerCommissionRule::categoryClassFor('travel'));
    }

    public function test_backfill_is_idempotent(): void
    {
        $m = $this->marketer();
        $cat = Category::factory()->create();
        DB::table('marketer_category_commissions')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'marketer_id' => $m->id, 'category_id' => $cat->id,
            'commission_mode' => 'both', 'commission_rate' => 10, 'commission_flat_amount' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mig = require database_path('migrations/2026_09_23_000001_create_marketer_commission_rules_table.php');
        $mig->up();
        $mig->up();
        $rules = MarketerCommissionRule::where('marketer_id', $m->id)->get();
        $this->assertCount(1, $rules);
        $this->assertSame(15, $rules->first()->resolveAmount(100));
        $this->assertSame('products', $rules->first()->scope);
    }
}
