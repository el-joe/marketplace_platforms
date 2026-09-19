<?php

namespace Tests\Feature;

use App\Models\MarketerAdmin;
use App\Models\MarketerListing;
use App\Models\ProductPromoBadge;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class PromoBadgePartnerMarketerTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->vendor->forceFill(['onboarding_completed_at' => now()])->save();
    }

    private function vendorAdmin(Vendor $vendor, array $perms = ['listings.view', 'listings.edit']): VendorAdmin
    {
        $a = VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'V', 'email' => 'v-' . Str::random(8) . '@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach ($perms as $p) {
            $a->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'vendor']));
        }

        return $a;
    }

    private function marketerAdmin($marketer): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'M', 'email' => 'm-' . Str::random(8) . '@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function otherVendor(): Vendor
    {
        $v = $this->s->vendor->replicate();
        $v->email = 'o-' . Str::random(6) . '@example.test';
        $v->store_name = 'Other ' . Str::random(6);
        $v->store_slug = 'o-' . Str::random(8);
        $v->onboarding_completed_at = now();
        $v->phone = '+9715' . fake()->numerify('########');
        $v->save();

        return $v;
    }

    private function otherMarketer()
    {
        $m = $this->s->marketer->replicate();
        $m->phone = '+9715' . fake()->numerify('########');
        $m->email = 'om-' . Str::random(6) . '@example.test';
        $m->save();

        return $m;
    }

    private function payload(array $over = []): array
    {
        return ['promo_badges' => [array_merge([
            'label_en' => 'Hot', 'label_ar' => 'ساخن', 'icon_key' => 'Star',
            'color_hex' => '#FF0000', 'text_color_hex' => '#FFFFFF', 'is_active' => 1,
        ], $over)]];
    }

    private function vUrl(string $id): string
    {
        return route('partner.listings.promo-badges.update', $id);
    }

    // ── Vendor web ────────────────────────────────────────────────────────

    public function test_vendor_saves_badges_on_own_listing_with_only_vendor_owner_column(): void
    {
        $l = $this->s->vendorListingFbp;
        $this->actingAs($this->vendorAdmin($this->s->vendor), 'vendor')
            ->put($this->vUrl($l->id), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        $b = ProductPromoBadge::sole();
        $this->assertSame($l->id, $b->vendor_listing_id);
        $this->assertNull($b->admin_listing_id);
        $this->assertNull($b->marketer_listing_id);
        $this->assertSame($l->productVariant->product_id, $b->product_id);
    }

    public function test_other_vendor_cannot_edit(): void
    {
        $this->actingAs($this->vendorAdmin($this->otherVendor()), 'vendor')
            ->put($this->vUrl($this->s->vendorListingFbp->id), $this->payload())->assertForbidden();
        $this->assertSame(0, ProductPromoBadge::count());
    }

    public function test_vendor_without_listings_edit_permission_is_denied(): void
    {
        $this->actingAs($this->vendorAdmin($this->s->vendor, ['listings.view']), 'vendor')
            ->put($this->vUrl($this->s->vendorListingFbp->id), $this->payload());
        $this->assertSame(0, ProductPromoBadge::count());
    }

    public function test_archived_listing_is_blocked_rejected_is_allowed(): void
    {
        $l = $this->s->vendorListingFbp;
        $admin = $this->vendorAdmin($this->s->vendor);

        $l->update(['status' => 'archived']);
        $this->actingAs($admin, 'vendor')->put($this->vUrl($l->id), $this->payload())->assertForbidden();

        $l->update(['status' => 'rejected']);
        $this->actingAs($admin, 'vendor')->put($this->vUrl($l->id), $this->payload())->assertRedirect();
        $this->assertSame(1, ProductPromoBadge::count());
    }

    public function test_validation_rules(): void
    {
        $admin = $this->vendorAdmin($this->s->vendor);
        $url = $this->vUrl($this->s->vendorListingFbp->id);

        $cases = [
            'promo_badges.0.icon_key' => $this->payload(['icon_key' => 'NotAnIcon']),
            'promo_badges.0.color_hex' => $this->payload(['color_hex' => 'red']),
            'promo_badges.0.text_color_hex' => $this->payload(['text_color_hex' => '#FFF']),
            'promo_badges.0.label_ar' => $this->payload(['label_ar' => '']),
            'promo_badges.0.label_en' => $this->payload(['label_en' => '']),
            'promo_badges' => ['promo_badges' => array_fill(0, 11, $this->payload()['promo_badges'][0])],
        ];
        foreach ($cases as $field => $body) {
            $this->actingAs($admin, 'vendor')->put($url, $body)->assertSessionHasErrors($field);
        }
        $this->assertSame(0, ProductPromoBadge::count());
    }

    public function test_ten_badges_accepted(): void
    {
        $this->actingAs($this->vendorAdmin($this->s->vendor), 'vendor')
            ->put($this->vUrl($this->s->vendorListingFbp->id), ['promo_badges' => array_fill(0, 10, $this->payload()['promo_badges'][0])])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, ProductPromoBadge::count());
    }

    // ── Marketer web ──────────────────────────────────────────────────────

    public function test_marketer_saves_on_own_listing_with_only_marketer_owner_column(): void
    {
        $l = $this->s->marketerListing;
        $admin = $this->marketerAdmin($this->s->marketer);
        $this->actingAs($admin, 'marketer')->put(route('marketer.listings.promo-badges.update', $l->id), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $b = ProductPromoBadge::sole();
        $this->assertSame($l->id, $b->marketer_listing_id);
        $this->assertNull($b->vendor_listing_id);
        $this->assertNull($b->admin_listing_id);
        $this->actingAs($admin, 'marketer')->get(route('marketer.listings.promo-badges.edit', $l->id))->assertOk();
    }

    public function test_marketer_cannot_touch_others_listing(): void
    {
        $admin = $this->marketerAdmin($this->otherMarketer());
        $l = $this->s->marketerListing;
        $this->actingAs($admin, 'marketer')->put(route('marketer.listings.promo-badges.update', $l->id), $this->payload())->assertForbidden();
        $this->actingAs($admin, 'marketer')->get(route('marketer.listings.promo-badges.edit', $l->id))->assertForbidden();
        $this->assertSame(0, ProductPromoBadge::count());
    }

    public function test_marketer_non_variant_listing_is_forbidden(): void
    {
        $l = $this->s->marketerListing;
        $l->product_variant_id = null;
        $l->listing_category = 'travel';
        $l->saveQuietly();
        $admin = $this->marketerAdmin($this->s->marketer);
        $this->actingAs($admin, 'marketer')->put(route('marketer.listings.promo-badges.update', $l->id), $this->payload())->assertForbidden();
        $this->assertSame(0, ProductPromoBadge::count());
    }

    // ── APIs ──────────────────────────────────────────────────────────────

    public function test_vendor_api_get_put_and_shape(): void
    {
        $l = $this->s->vendorListingFbp;
        $admin = $this->vendorAdmin($this->s->vendor);
        $url = "/api/vendor/v1/listings/{$l->id}/promo-badges";

        $this->actingAs($admin, 'vendor')->putJson($url, $this->payload())->assertOk()
            ->assertJsonPath('data.0.label.ar', 'ساخن')->assertJsonPath('data.0.is_active', true);
        $this->actingAs($admin, 'vendor')->getJson($url)->assertOk()
            ->assertJsonStructure(['data' => [['id', 'label' => ['ar', 'en'], 'icon_key', 'color_hex', 'text_color_hex', 'sort_order', 'is_active']]]);
        $this->assertNotNull(ProductPromoBadge::sole()->vendor_listing_id);

        $this->actingAs($admin, 'vendor')->putJson($url, $this->payload(['icon_key' => 'Bogus']))->assertStatus(422);
    }

    public function test_vendor_api_authorization(): void
    {
        $l = $this->s->vendorListingFbp;
        $url = "/api/vendor/v1/listings/{$l->id}/promo-badges";

        $this->actingAs($this->vendorAdmin($this->otherVendor()), 'vendor')->putJson($url, $this->payload())->assertForbidden();
        $this->actingAs($this->vendorAdmin($this->s->vendor, ['listings.view']), 'vendor')->putJson($url, $this->payload())->assertForbidden();
        $this->assertSame(0, ProductPromoBadge::count());
    }

    public function test_marketer_api_get_put_and_authorization(): void
    {
        $l = $this->s->marketerListing;
        $url = "/api/marketer/listings/{$l->id}/promo-badges";
        $mine = $this->marketerAdmin($this->s->marketer);

        $this->actingAs($mine, 'marketer_api')->putJson($url, $this->payload())->assertOk()
            ->assertJsonPath('data.0.is_active', true);
        $this->actingAs($mine, 'marketer_api')->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotNull(ProductPromoBadge::sole()->marketer_listing_id);

        $this->actingAs($mine, 'marketer_api')->putJson($url, $this->payload(['color_hex' => 'x']))->assertStatus(422);

        $theirs = $this->marketerAdmin($this->otherMarketer());
        $this->actingAs($theirs, 'marketer_api')->putJson($url, $this->payload())->assertForbidden();
        $this->actingAs($theirs, 'marketer_api')->getJson($url)->assertForbidden();
    }
}
