<?php

namespace Tests\Feature\Ads;

use App\Http\Controllers\Partner\AdSubscriptionController;
use App\Models\VendorAdSubscription;
use App\Models\VendorListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LegacyAdSubscriptionBlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_returns_410_and_grants_nothing(): void
    {
        $response = app(AdSubscriptionController::class)->subscribe(Request::create('/', 'POST', [
            'vendor_listing_id' => (string) \Illuminate\Support\Str::uuid(),
            'ad_package_id' => (string) \Illuminate\Support\Str::uuid(),
        ]));

        $this->assertSame(410, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertFalse($body['success']);
        $this->assertSame(route('partner.ad-slots.index'), $body['redirect']);
        $this->assertSame(0, VendorAdSubscription::count());
        $this->assertSame(0, VendorListing::where('is_ad_boosted', true)->count());
    }
}
