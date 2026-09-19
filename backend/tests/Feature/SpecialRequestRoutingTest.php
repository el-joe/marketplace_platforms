<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CustomerSpecialRequest;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerProfile;
use App\Notifications\Marketer\BrokerSpecialRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class SpecialRequestRoutingTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;
    private City $cityY;
    private Category $otherCategory;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);
        $this->cityY = City::create([
            'country_id' => $this->s->country->id, 'name_ar' => 'ابوظبي', 'name_en' => 'Abu Dhabi',
            'shipping_zone_id' => $this->s->shippingZone->id, 'is_active' => true, 'cod_available' => true,
        ]);
        $this->otherCategory = Category::factory()->create();
        RateLimiter::clear('special-request:' . $this->s->customer->id);
    }

    private function broker(array $profile = [], array $marketer = []): MarketerAdmin
    {
        $m = Marketer::create(array_merge([
            'name' => 'B ' . Str::random(4), 'email' => Str::random(8) . '@example.test',
            'phone' => '+9715' . fake()->numerify('########'), 'marketer_type' => 'affiliate',
            'global_status' => 'active', 'country_id' => $this->s->country->id, 'approved_at' => now(),
        ], $marketer));
        MarketerProfile::create(array_merge(['marketer_id' => $m->id], $profile));

        return MarketerAdmin::create([
            'marketer_id' => $m->id, 'name' => 'A', 'email' => Str::random(8) . '@example.test',
            'password' => Hash::make('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function store(array $over = [])
    {
        $this->actingAs($this->s->customer, 'customer');

        return $this->postJson("/api/customer/v1/{$this->s->country->site_code}/special-requests", array_merge([
            'category_id' => $this->s->category->id,
            'city_id' => $this->s->city->id,
            'title_en' => 'Need a thing',
            'description_en' => 'Details here',
        ], $over));
    }

    private function req(?string $cityId, ?string $catId = null, string $status = 'open', $customer = null): CustomerSpecialRequest
    {
        return CustomerSpecialRequest::create([
            'customer_id' => ($customer ?? $this->s->customer)->id,
            'category_id' => $catId ?? $this->s->category->id, 'city_id' => $cityId,
            'title_en' => 'T', 'description_en' => 'D', 'status' => $status,
        ]);
    }

    public function test_store_validates(): void
    {
        $this->store(['title_en' => ''])->assertStatus(422);
        $this->store(['category_id' => (string) Str::uuid()])->assertStatus(422);
    }

    public function test_store_returns_brokers_notified_and_routes_by_city_and_category(): void
    {
        $cat = $this->s->category->id;
        $x = $this->broker(['broker_category_id' => $cat, 'broker_city_id' => $this->s->city->id]);
        $all = $this->broker(['broker_category_id' => $cat, 'broker_serves_all_cities' => true]);
        $y = $this->broker(['broker_category_id' => $cat, 'broker_city_id' => $this->cityY->id]);
        $other = $this->broker(['broker_category_id' => $this->otherCategory->id, 'broker_serves_all_cities' => true]);

        $res = $this->store();
        $res->assertStatus(201)->assertJsonPath('data.brokers_notified', 2);

        Notification::assertSentTo($x, BrokerSpecialRequestNotification::class);
        Notification::assertSentTo($all, BrokerSpecialRequestNotification::class);
        Notification::assertNotSentTo($y, BrokerSpecialRequestNotification::class);
        Notification::assertNotSentTo($other, BrokerSpecialRequestNotification::class);
    }

    public function test_null_city_notifies_every_category_broker(): void
    {
        $cat = $this->s->category->id;
        $x = $this->broker(['broker_category_id' => $cat, 'broker_city_id' => $this->s->city->id]);
        $y = $this->broker(['broker_category_id' => $cat, 'broker_city_id' => $this->cityY->id]);
        $other = $this->broker(['broker_category_id' => $this->otherCategory->id, 'broker_serves_all_cities' => true]);

        $this->store(['city_id' => null])->assertStatus(201)->assertJsonPath('data.brokers_notified', 2);
        Notification::assertSentTo([$x, $y], BrokerSpecialRequestNotification::class);
        Notification::assertNotSentTo($other, BrokerSpecialRequestNotification::class);
    }

    public function test_non_affiliate_and_inactive_never_notified(): void
    {
        $p = ['broker_category_id' => $this->s->category->id, 'broker_serves_all_cities' => true];
        $vendorType = $this->broker($p, ['marketer_type' => 'influencer']);
        $inactive = $this->broker($p, ['global_status' => 'suspended']);

        $this->store()->assertStatus(201)->assertJsonPath('data.brokers_notified', 0);
        Notification::assertNotSentTo($vendorType, BrokerSpecialRequestNotification::class);
        Notification::assertNotSentTo($inactive, BrokerSpecialRequestNotification::class);
    }

    public function test_notification_sent_once_per_marketer_admin(): void
    {
        $a1 = $this->broker(['broker_category_id' => $this->s->category->id, 'broker_serves_all_cities' => true]);
        $a2 = MarketerAdmin::create([
            'marketer_id' => $a1->marketer_id, 'name' => 'A2', 'email' => Str::random(8) . '@example.test',
            'password' => Hash::make('password'), 'is_owner' => false, 'is_active' => true,
        ]);

        $this->store()->assertStatus(201);
        Notification::assertSentToTimes($a1, BrokerSpecialRequestNotification::class, 1);
        Notification::assertSentToTimes($a2, BrokerSpecialRequestNotification::class, 1);
    }

    public function test_marketer_index_show_and_api_use_identical_matching(): void
    {
        $cat = $this->s->category->id;
        $noCity = $this->broker(['broker_category_id' => $cat, 'broker_serves_all_cities' => false]);
        $mine = $this->broker(['broker_category_id' => $cat, 'broker_city_id' => $this->s->city->id]);

        $inCity = $this->req($this->s->city->id);
        $otherCity = $this->req($this->cityY->id);
        $noCityReq = $this->req(null);
        $closed = $this->req($this->s->city->id, null, 'closed');
        $wrongCat = $this->req($this->s->city->id, $this->otherCategory->id);

        // broker without city and serves_all=false only sees city-less requests
        $this->actingAs($noCity, 'marketer')->get(route('marketer.special-requests.index'))->assertOk();
        $this->actingAs($noCity, 'marketer')->get(route('marketer.special-requests.show', $noCityReq->id))->assertOk();
        $this->actingAs($noCity, 'marketer')->get(route('marketer.special-requests.show', $inCity->id))->assertNotFound();

        $this->actingAs($mine, 'marketer')->get(route('marketer.special-requests.show', $inCity->id))->assertOk();
        $this->actingAs($mine, 'marketer')->get(route('marketer.special-requests.show', $noCityReq->id))->assertOk();
        foreach ([$otherCity, $closed, $wrongCat] as $r) {
            $this->actingAs($mine, 'marketer')->get(route('marketer.special-requests.show', $r->id))->assertNotFound();
        }

        // JSON parity
        $api = fn ($u) => $this->actingAs($u, 'marketer_api');
        $ids = collect($api($mine)->getJson('/api/marketer/special-requests')->assertOk()->json('data.data'))->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$inCity->id, $noCityReq->id])->sort()->values()->all(), $ids);
        $api($mine)->getJson("/api/marketer/special-requests/{$otherCity->id}")->assertNotFound();
        $json = $api($mine)->getJson("/api/marketer/special-requests/{$inCity->id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('customer_email', $json);
        $this->assertArrayHasKey('customer_first_name', $json);

        $ids2 = collect($api($noCity)->getJson('/api/marketer/special-requests')->json('data.data'))->pluck('id')->all();
        $this->assertSame([$noCityReq->id], $ids2);
    }

    public function test_marketer_layout_and_profile_render_for_affiliate(): void
    {
        $b = $this->broker(['broker_category_id' => $this->s->category->id, 'broker_serves_all_cities' => true]);
        $this->actingAs($b, 'marketer')->get(route('marketer.special-requests.index'))->assertOk();
        $this->actingAs($b, 'marketer')->get(route('marketer.profile'))->assertOk();
    }

    public function test_customer_cannot_read_or_close_other_customers_request(): void
    {
        $other = Customer::create([
            'name' => 'O', 'email' => Str::random(8) . '@example.test', 'phone' => '+9715' . fake()->numerify('########'),
            'password' => Hash::make('password'), 'country_id' => $this->s->country->id, 'status' => 'active',
        ]);
        $r = $this->req(null, null, 'open', $other);
        $this->actingAs($this->s->customer, 'customer');
        $base = "/api/customer/v1/{$this->s->country->site_code}/special-requests/{$r->id}";
        $this->getJson($base)->assertNotFound();
        $this->patchJson("$base/close")->assertNotFound();
        $mine = $this->req(null);
        $this->getJson("/api/customer/v1/{$this->s->country->site_code}/special-requests/{$mine->id}")->assertOk();
    }

    public function test_close_state_guard(): void
    {
        $open = $this->req(null);
        $closed = $this->req(null, null, 'closed');
        $this->actingAs($this->s->customer, 'customer');
        $b = "/api/customer/v1/{$this->s->country->site_code}/special-requests";
        $this->patchJson("$b/{$open->id}/close")->assertOk();
        $this->assertSame('closed', $open->fresh()->status);
        $this->patchJson("$b/{$closed->id}/close")->assertStatus(422);
    }

    public function test_store_is_throttled_after_ten(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->store()->assertStatus(201);
        }
        $this->store()->assertStatus(429);
    }

    public function test_guest_cannot_create_special_request(): void
    {
        $this->postJson("/api/customer/v1/{$this->s->country->site_code}/special-requests", [
            'category_id' => $this->s->category->id, 'title_en' => 'x', 'description_en' => 'y',
        ])->assertUnauthorized();
    }

    public function test_in_progress_request_can_be_closed_and_leaves_broker_panel(): void
    {
        $b = $this->broker(['broker_category_id' => $this->s->category->id, 'broker_serves_all_cities' => true]);
        $r = $this->req(null, null, 'in_progress');
        $this->actingAs($b, 'marketer')->get(route('marketer.special-requests.show', $r->id))->assertNotFound();
        $this->actingAs($this->s->customer, 'customer');
        $this->patchJson("/api/customer/v1/{$this->s->country->site_code}/special-requests/{$r->id}/close")->assertOk();
        $this->assertSame('closed', $r->fresh()->status);
    }

    public function test_blade_panel_index_lists_matching_and_show_renders(): void
    {
        $b = $this->broker(['broker_category_id' => $this->s->category->id, 'broker_serves_all_cities' => true]);
        $r = $this->req(null);
        $this->actingAs($b, 'marketer')->get(route('marketer.special-requests.index'))->assertOk();
        $this->actingAs($b, 'marketer')->get(route('marketer.special-requests.show', $r->id))->assertOk()->assertSee('T');
    }
}
