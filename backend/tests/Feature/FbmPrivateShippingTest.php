<?php

namespace Tests\Feature;

use App\Enums\FulfillmentModel;
use App\Models\ShippingCompany;
use App\Models\VendorListing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FbmPrivateShippingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_visible_to_scope_and_columns(): void
    {
        $sql = ShippingCompany::visibleTo('v1')->toSql();
        $this->assertStringContainsString('owner_vendor_id', $sql);
        $this->assertTrue(\Schema::hasColumn('vendor_listings', 'fbm_payment_gateway_id'));
        $this->assertTrue(\Schema::hasColumn('shipping_companies', 'owner_vendor_id'));
        $this->assertSame(FulfillmentModel::Fbm, (new VendorListing)->fill(['fulfillment_model' => 'fbm'])->fulfillment_model);
    }
}
