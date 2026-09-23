<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\ExclusiveContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ExclusiveContractTest extends TestCase
{
    use RefreshDatabase;

    private function setUpData(): array
    {
        $s = MarketplaceScenario::make()->build();
        foreach (['vendors.assigned_only', 'marketers.view', 'marketers.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.view', 'marketers.manage']);

        $cat = ClassifiedCategory::create(['name_en' => 'Cat', 'name_ar' => 'قسم', 'slug' => 'cat-'.Str::random(5), 'is_active' => true]);
        $listing = new ClassifiedListing;
        $listing->forceFill([
            'listing_number' => 'L'.Str::random(8), 'slug' => Str::random(10),
            'seller_type' => 'x', 'seller_id' => (string) Str::uuid(),
            'classified_category_id' => $cat->id, 'country_id' => $s->country->id,
            'listing_purpose' => 'sale', 'title_en' => 't', 'title_ar' => 't',
            'price' => 100, 'currency' => 'AED', 'status' => 'active',
        ])->save();

        return [$s, $admin, $cat, $listing];
    }

    private function payload(ClassifiedListing $l, string $from, string $to): array
    {
        return ['classified_listing_id' => $l->id, 'starts_at' => $from, 'ends_at' => $to, 'status' => 'active'];
    }

    public function test_store_autofills_category_and_rejects_overlap(): void
    {
        [$s, $admin, $cat, $listing] = $this->setUpData();
        $url = route('admin.marketers.exclusive-contracts.store', $s->marketer->id);

        $this->actingAs($admin, 'admin')->post($url, $this->payload($listing, '2027-01-01', '2027-02-01'))
            ->assertSessionHasNoErrors();
        $this->assertSame($cat->id, ExclusiveContract::first()->classified_category_id);

        $this->actingAs($admin, 'admin')->post($url, $this->payload($listing, '2027-01-15', '2027-03-01'))
            ->assertSessionHasErrors('classified_listing_id');
        $this->assertSame(1, ExclusiveContract::count());

        $this->actingAs($admin, 'admin')->post($url, $this->payload($listing, '2027-02-01', '2027-03-01'))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ExclusiveContract::count());
    }

    public function test_end_before_start_rejected(): void
    {
        [$s, $admin, , $listing] = $this->setUpData();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.marketers.exclusive-contracts.store', $s->marketer->id), $this->payload($listing, '2027-02-01', '2027-01-01'))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_expire_command_activates_and_expires(): void
    {
        [$s, $admin, $cat] = $this->setUpData();
        $mk = fn ($status, $from, $to) => ExclusiveContract::create([
            'marketer_id' => $s->marketer->id, 'classified_category_id' => $cat->id,
            'starts_at' => now()->modify($from), 'ends_at' => now()->modify($to),
            'status' => $status, 'created_by' => $admin->id,
        ]);
        $pending = $mk('pending', '-1 day', '+5 days');
        $old = $mk('active', '-10 days', '-1 day');
        $future = $mk('pending', '+2 days', '+9 days');

        $this->artisan('exclusive-contracts:expire')->assertSuccessful();

        $this->assertSame('active', $pending->fresh()->status);
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame(1, ExclusiveContract::active()->count());
    }
}
