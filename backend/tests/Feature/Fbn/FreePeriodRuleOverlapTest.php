<?php

namespace Tests\Feature\Fbn;

use App\Models\Admin;
use App\Models\StorageFeeFreePeriodRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #10: free-period weight ranges are closed intervals in grams,
 * NULL max = and above. Overlap iff newMin <= existingMax AND existingMin <= newMax.
 */
class FreePeriodRuleOverlapTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private StorageFeeFreePeriodRule $openEndedRule;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['fbn_storage_fees.view', 'fbn_storage_fees.manage']);

        // The table migration seeds default tiers; pin the client's scenario.
        StorageFeeFreePeriodRule::query()->delete();
        $this->openEndedRule = StorageFeeFreePeriodRule::create(['min_weight_grams' => 1000, 'max_weight_grams' => null, 'free_days' => 30]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeRule(array $payload): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->postJson(
            route('admin.fbn.storage-fees.free-period-rules.store'),
            $payload + ['free_days' => 15, 'open_ended' => false],
        );
    }

    /**
     * @return array<string, array{int, ?int}>
     */
    public static function acceptedRanges(): array
    {
        return [
            'adjacent below (0-999)' => [0, 999],
            'disjoint below (0-500)' => [0, 500],
            'single gram just below' => [999, 999],
        ];
    }

    #[DataProvider('acceptedRanges')]
    public function test_ranges_below_an_open_ended_rule_are_accepted(int $min, ?int $max): void
    {
        $this->storeRule(['min_weight_grams' => $min, 'max_weight_grams' => $max])->assertOk();

        $this->assertDatabaseHas('storage_fee_free_period_rules', ['min_weight_grams' => $min, 'max_weight_grams' => $max, 'free_days' => 15]);
    }

    /**
     * @return array<string, array{int, ?int, bool}>
     */
    public static function rejectedRanges(): array
    {
        return [
            'shared boundary 1000' => [0, 1000, false],
            'overlapping' => [500, 1500, false],
            'contained' => [2000, 3000, false],
            'open-ended both sides' => [0, null, true],
            'open-ended above' => [5000, null, true],
        ];
    }

    #[DataProvider('rejectedRanges')]
    public function test_ranges_sharing_a_gram_with_an_existing_rule_are_rejected_naming_it(int $min, ?int $max, bool $openEnded): void
    {
        $this->storeRule(['min_weight_grams' => $min, 'max_weight_grams' => $max, 'open_ended' => $openEnded])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['min_weight_grams' => __('admin.fbn_section.rule_overlap', [
                'range' => $this->openEndedRule->weightRangeLabel(),
                'days' => 30,
            ])]);

        $this->assertSame(1, StorageFeeFreePeriodRule::count());
    }

    public function test_a_range_containing_an_existing_closed_rule_is_rejected(): void
    {
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 200, 'max_weight_grams' => 300, 'free_days' => 10]);

        $this->storeRule(['min_weight_grams' => 0, 'max_weight_grams' => 999])->assertJsonValidationErrors('min_weight_grams');
        $this->storeRule(['min_weight_grams' => 301, 'max_weight_grams' => 999])->assertOk();
    }

    public function test_a_range_above_a_closed_rule_is_accepted(): void
    {
        $this->openEndedRule->delete();
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 60]);

        $this->storeRule(['min_weight_grams' => 1000, 'max_weight_grams' => null, 'open_ended' => true])->assertOk();
    }

    public function test_a_rule_can_be_updated_without_conflicting_with_itself_but_not_into_another(): void
    {
        $lower = StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 60]);
        $url = route('admin.fbn.storage-fees.free-period-rules.update', $lower);

        $this->actingAs($this->admin, 'admin')
            ->putJson($url, ['min_weight_grams' => 0, 'max_weight_grams' => 999, 'open_ended' => false, 'free_days' => 15])
            ->assertOk();
        $this->assertSame(15, $lower->fresh()->free_days);

        $this->actingAs($this->admin, 'admin')
            ->putJson($url, ['min_weight_grams' => 0, 'max_weight_grams' => 1000, 'open_ended' => false, 'free_days' => 15])
            ->assertJsonValidationErrors('min_weight_grams');
        $this->assertSame(999, $lower->fresh()->max_weight_grams);
    }

    public function test_an_empty_max_without_open_ended_is_rejected_instead_of_becoming_and_above(): void
    {
        $this->storeRule(['min_weight_grams' => 0, 'max_weight_grams' => ''])
            ->assertJsonValidationErrors(['max_weight_grams' => __('admin.fbn_section.max_weight_required')]);
    }

    public function test_arabic_indic_digits_are_read_as_numbers(): void
    {
        $this->storeRule(['min_weight_grams' => '٠', 'max_weight_grams' => '٩٩٩', 'free_days' => '١٥'])->assertOk();

        $this->assertDatabaseHas('storage_fee_free_period_rules', ['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 15]);
    }

    public function test_the_overlap_error_is_arabic_in_the_arabic_panel(): void
    {
        app()->setLocale('ar');

        $response = $this->storeRule(['min_weight_grams' => 0, 'max_weight_grams' => 1000]);

        $message = $response->json('errors.min_weight_grams.0');
        $this->assertStringContainsString('يتداخل', $message);
        $this->assertStringContainsString('1,000 غرام فأكثر', $message);
    }

    public function test_the_rule_form_renders_the_input_ids_its_script_reads(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.fbn.storage-fees.free-period-rules.index'))
            ->assertOk()
            ->assertSee('id="rule-min-weight"', false)
            ->assertSee('id="rule-max-weight"', false)
            ->assertSee('id="rule-free-days"', false)
            ->assertSee('for="rule-min-weight"', false);
    }
}
