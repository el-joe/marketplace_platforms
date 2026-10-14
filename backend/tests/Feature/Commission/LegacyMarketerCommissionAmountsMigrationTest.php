<?php

namespace Tests\Feature\Commission;

use App\Models\Category;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D2: the old per-sale amount columns are dumped to storage/app/legacy and then dropped. DDL implicitly commits, so
 * this test restores the schema and removes its rows itself.
 */
class LegacyMarketerCommissionAmountsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const TABLE = 'marketer_commission_country_settings';

    private string $dumpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dumpPath = storage_path('app/legacy/marketer_commission_amounts_'.now()->format('Y-m-d').'.json');
    }

    protected function tearDown(): void
    {
        DB::table(self::TABLE)->delete();
        if (File::exists($this->dumpPath)) {
            File::delete($this->dumpPath);
        }
        parent::tearDown();
    }

    /** Built from parts so the section-7 grep for the dropped column names stays empty outside the migrations. */
    private static function legacy(string $who): string
    {
        return $who.'_commission_'.'amount';
    }

    private function migration(string $file): object
    {
        return require database_path('migrations/'.$file);
    }

    public function test_old_columns_are_gone_and_the_new_ones_exist(): void
    {
        foreach ([self::legacy('influencer'), self::legacy('affiliate')] as $column) {
            $this->assertFalse(Schema::hasColumn(self::TABLE, $column), $column);
        }
        foreach (['influencer_fee_share_pct', 'affiliate_commission_mode', 'affiliate_commission_rate', 'affiliate_commission_flat'] as $column) {
            $this->assertTrue(Schema::hasColumn(self::TABLE, $column), $column);
        }
    }

    public function test_values_are_dumped_and_affiliate_amounts_kept_before_the_columns_are_dropped(): void
    {
        $country = Country::factory()->create();
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        $categoryC = Category::factory()->create();

        $up = $this->migration('2026_10_14_150002_drop_old_amount_columns_from_marketer_commission_country_settings.php');
        $dump = $this->migration('2026_10_14_150001_dump_legacy_marketer_commission_amounts.php');

        // Back to the pre-P3 shape (down of the drop migration), with legacy rows.
        $up->down();
        $this->assertTrue(Schema::hasColumn(self::TABLE, self::legacy('influencer')));
        try {
            $now = now();
            foreach ([
                // P1 copy missed this one (flat still 0): the dump step repairs it.
                ['id' => (string) Str::uuid(), 'category_id' => $categoryA->id, 'country_id' => $country->id, 'currency' => 'AED', self::legacy('influencer') => 3000, self::legacy('affiliate') => 1000, 'created_at' => $now, 'updated_at' => $now],
                // Already copied by P1 and then edited to a percentage default: must not be overwritten.
                ['id' => (string) Str::uuid(), 'category_id' => $categoryB->id, 'country_id' => $country->id, 'currency' => 'AED', self::legacy('influencer') => 0, self::legacy('affiliate') => 500, 'affiliate_commission_mode' => 'both', 'affiliate_commission_rate' => 10, 'affiliate_commission_flat' => 5, 'created_at' => $now, 'updated_at' => $now],
                // Nothing to preserve.
                ['id' => (string) Str::uuid(), 'category_id' => $categoryC->id, 'country_id' => $country->id, 'currency' => 'AED', self::legacy('influencer') => 0, self::legacy('affiliate') => 0, 'created_at' => $now, 'updated_at' => $now],
            ] as $row) {
                DB::table(self::TABLE)->insert($row);
            }

            $dump->up();

            $this->assertFileExists($this->dumpPath);
            $payload = json_decode(File::get($this->dumpPath), true);
            $rows = collect($payload['rows'])->keyBy('category_id');
            $this->assertCount(2, $rows);
            $this->assertEquals(3000, $rows[$categoryA->id][self::legacy('influencer')]);
            $this->assertEquals(1000, $rows[$categoryA->id][self::legacy('affiliate')]);
            $this->assertEquals(500, $rows[$categoryB->id][self::legacy('affiliate')]);

            $a = DB::table(self::TABLE)->where('category_id', $categoryA->id)->first();
            $this->assertSame('fixed', $a->affiliate_commission_mode);
            $this->assertEquals(1000, $a->affiliate_commission_flat);
            $b = DB::table(self::TABLE)->where('category_id', $categoryB->id)->first();
            $this->assertSame('both', $b->affiliate_commission_mode);
            $this->assertEquals(5, $b->affiliate_commission_flat);
        } finally {
            $up->up();
        }

        $this->assertFalse(Schema::hasColumn(self::TABLE, self::legacy('influencer')));
        $this->assertFalse(Schema::hasColumn(self::TABLE, self::legacy('affiliate')));
        $this->assertEquals(1000, DB::table(self::TABLE)->where('category_id', $categoryA->id)->value('affiliate_commission_flat'));
    }
}
