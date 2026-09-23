<?php

namespace Tests\Feature;

use App\Models\StorageFeeFreePeriodRule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FbnVolumetricStorageFeeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_columns_exist_and_free_days_lookup(): void
    {
        foreach (['chargeable_weight_grams', 'volumetric_weight_grams', 'within_free_period', 'days_in_storage'] as $c) {
            $this->assertTrue(Schema::hasColumn('fbn_storage_fees', $c), $c);
        }
        $this->assertTrue(Schema::hasColumn('warehouse_inventories', 'first_stocked_at'));
        $this->assertIsInt(StorageFeeFreePeriodRule::freeDaysFor(500));
    }
}
