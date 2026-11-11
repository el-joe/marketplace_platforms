<?php

namespace Tests\Feature\ProductRequests;

use App\Enums\ProductRequestStatus;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\ProductRequest;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Partner and marketer panel pages for requesting a missing catalog product (stage 2).
 */
class PanelProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $this->s = MarketplaceScenario::make()->build();
        $this->s->vendor->forceFill(['onboarding_completed_at' => now()])->save();
        $this->s->marketer->forceFill(['onboarding_completed_at' => now()])->save();
    }

    private function vendorAdmin(?Vendor $vendor = null, array $permissions = ['listings.view', 'listings.create']): VendorAdmin
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => ($vendor ?? $this->s->vendor)->id, 'name' => 'Vendor User', 'email' => 'v-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']));
        }

        return $vendorAdmin;
    }

    private function marketerAdmin(?Marketer $marketer = null): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => ($marketer ?? $this->s->marketer)->id, 'name' => 'Marketer User', 'email' => 'm-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function otherVendor(): Vendor
    {
        $vendor = $this->s->vendor->replicate();
        $vendor->email = 'o-'.Str::random(6).'@example.test';
        $vendor->store_name = 'Other '.Str::random(6);
        $vendor->store_slug = 'o-'.Str::random(8);
        $vendor->phone = '+9715'.fake()->numerify('########');
        $vendor->save();

        return $vendor;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Super Blender 3000',
            'name_ar' => 'خلاط سوبر 3000',
            'category_id' => $this->s->category->id,
            'brand_id' => $this->s->brand->id,
            'gtin' => '6291041500213',
            'description' => 'A powerful 1200W kitchen blender with a 2 litre glass jar and five speeds.',
            'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
        ], $overrides);
    }

    public function test_vendor_submits_request_and_sees_it_in_index_and_details(): void
    {
        $vendorAdmin = $this->vendorAdmin();

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.product-requests.create', ['q' => 'Super Blender']))
            ->assertOk()
            ->assertSee('value="Super Blender"', false)
            ->assertSee('name="images[]"', false);

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->post(route('partner.product-requests.store'), $this->payload());

        $productRequest = ProductRequest::sole();
        $response->assertRedirect(route('partner.product-requests.show', $productRequest->id))
            ->assertSessionHas('success', __('product_requests.messages.submitted', ['number' => $productRequest->request_number]));

        $this->assertSame($this->s->vendor->id, $productRequest->vendor_id);
        $this->assertSame(ProductRequestStatus::Pending, $productRequest->status);
        $this->assertCount(2, $productRequest->images);
        Storage::disk('public')->assertExists($productRequest->images[0]);

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.product-requests.index'))
            ->assertOk()
            ->assertSee($productRequest->request_number)
            ->assertSee($productRequest->displayName());

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.product-requests.show', $productRequest->id))
            ->assertOk()
            ->assertSee($productRequest->request_number)
            ->assertSee(route('partner.product-requests.cancel', $productRequest->id), false);
    }

    public function test_vendor_cannot_see_or_cancel_another_vendors_request(): void
    {
        $foreignRequest = ProductRequest::factory()->vendor($this->otherVendor())->create();
        $vendorAdmin = $this->vendorAdmin();

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.product-requests.show', $foreignRequest->id))
            ->assertNotFound();

        $this->actingAs($vendorAdmin, 'vendor')
            ->post(route('partner.product-requests.cancel', $foreignRequest->id))
            ->assertNotFound();

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.product-requests.index'))
            ->assertOk()
            ->assertDontSee($foreignRequest->request_number);

        $this->assertSame(ProductRequestStatus::Pending, $foreignRequest->fresh()->status);
    }

    public function test_vendor_can_cancel_pending_request_but_not_approved_one(): void
    {
        $vendorAdmin = $this->vendorAdmin();
        $pendingRequest = ProductRequest::factory()->vendor($this->s->vendor)->create();
        $approvedRequest = ProductRequest::factory()->vendor($this->s->vendor)->approved()->create();

        $this->actingAs($vendorAdmin, 'vendor')
            ->post(route('partner.product-requests.cancel', $pendingRequest->id))
            ->assertRedirect(route('partner.product-requests.show', $pendingRequest->id))
            ->assertSessionHas('success');
        $this->assertSame(ProductRequestStatus::Cancelled, $pendingRequest->fresh()->status);

        $this->actingAs($vendorAdmin, 'vendor')
            ->from(route('partner.product-requests.show', $approvedRequest->id))
            ->post(route('partner.product-requests.cancel', $approvedRequest->id))
            ->assertRedirect(route('partner.product-requests.show', $approvedRequest->id))
            ->assertSessionHas('error', __('product_requests.messages.cannot_cancel'));
        $this->assertSame(ProductRequestStatus::Approved, $approvedRequest->fresh()->status);
    }

    public function test_approved_request_links_to_listing_wizard_for_the_product(): void
    {
        $approvedRequest = ProductRequest::factory()->vendor($this->s->vendor)->approved()->create();
        $product = $approvedRequest->product;

        $this->actingAs($this->vendorAdmin(), 'vendor')
            ->get(route('partner.product-requests.show', $approvedRequest->id))
            ->assertOk()
            ->assertSee(__('product_requests.panel.create_listing'))
            ->assertSee(e(route('partner.listings.create', ['q' => $product->gtin ?: $product->name_en])), false);
    }

    public function test_vendor_without_create_permission_cannot_submit(): void
    {
        $this->actingAs($this->vendorAdmin(permissions: ['listings.view']), 'vendor')
            ->post(route('partner.product-requests.store'), $this->payload());

        $this->assertSame(0, ProductRequest::count());
    }

    public function test_submission_requires_images_and_rejects_existing_gtin(): void
    {
        $this->s->product->forceFill(['gtin' => '6291041500213'])->save();
        $vendorAdmin = $this->vendorAdmin();

        $this->actingAs($vendorAdmin, 'vendor')
            ->from(route('partner.product-requests.create'))
            ->post(route('partner.product-requests.store'), $this->payload(['images' => []]))
            ->assertRedirect(route('partner.product-requests.create'))
            ->assertSessionHasErrors(['images', 'gtin' => __('product_requests.messages.gtin_exists')]);

        $this->assertSame(0, ProductRequest::count());
    }

    public function test_marketer_submits_and_views_own_request_only(): void
    {
        $marketerAdmin = $this->marketerAdmin();

        $this->actingAs($marketerAdmin, 'marketer')
            ->get(route('marketer.product-requests.create'))
            ->assertOk();

        $this->actingAs($marketerAdmin, 'marketer')
            ->post(route('marketer.product-requests.store'), $this->payload(['brand_id' => null, 'brand_name' => 'Blendo']))
            ->assertSessionHasNoErrors();

        $productRequest = ProductRequest::sole();
        $this->assertSame($this->s->marketer->id, $productRequest->marketer_id);
        $this->assertNull($productRequest->vendor_id);
        $this->assertSame('Blendo', $productRequest->brand_name);

        $this->actingAs($marketerAdmin, 'marketer')
            ->get(route('marketer.product-requests.index'))
            ->assertOk()
            ->assertSee($productRequest->request_number);

        $this->actingAs($marketerAdmin, 'marketer')
            ->get(route('marketer.product-requests.show', $productRequest->id))
            ->assertOk()
            ->assertSee('Blendo');

        $vendorRequest = ProductRequest::factory()->vendor($this->s->vendor)->create();
        $this->actingAs($marketerAdmin, 'marketer')
            ->get(route('marketer.product-requests.show', $vendorRequest->id))
            ->assertNotFound();
    }

    public function test_marketer_submission_rejects_bad_images_unsafe_links_and_duplicate_pending_requests(): void
    {
        $marketerAdmin = $this->marketerAdmin();
        $sevenImages = array_map(fn (int $i) => UploadedFile::fake()->image("photo-{$i}.jpg"), range(1, 7));

        $this->actingAs($marketerAdmin, 'marketer')
            ->post(route('marketer.product-requests.store'), $this->payload([
                'images' => $sevenImages,
                'reference_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors(['images', 'reference_url']);

        $this->actingAs($marketerAdmin, 'marketer')
            ->post(route('marketer.product-requests.store'), $this->payload([
                'images' => [UploadedFile::fake()->create('spec-sheet.pdf', 100, 'application/pdf')],
                'reference_url' => 'ftp://example.com/blender',
            ]))
            ->assertSessionHasErrors(['images.0', 'reference_url']);

        ProductRequest::factory()->marketer($this->s->marketer)->create(['gtin' => '6291041500213']);

        $this->actingAs($marketerAdmin, 'marketer')
            ->post(route('marketer.product-requests.store'), $this->payload())
            ->assertSessionHasErrors(['gtin' => __('product_requests.messages.duplicate_pending')]);

        $this->assertSame(1, ProductRequest::count());
    }
}
