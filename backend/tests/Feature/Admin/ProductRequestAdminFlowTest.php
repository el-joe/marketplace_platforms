<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductRequestStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Notifications\ProductRequestApproved;
use App\Notifications\ProductRequestRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Smoke coverage for the admin side of product requests (stage 1).
 */
class ProductRequestAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function vendorRequest(): ProductRequest
    {
        $vendor = Vendor::factory()->create();
        $vendorAdmin = VendorAdmin::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Vendor User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        Storage::disk('public')->put('product-requests/test/one.jpg', UploadedFile::fake()->image('one.jpg')->getContent());

        return ProductRequest::factory()->vendor($vendor)->create([
            'requested_by_type' => VendorAdmin::class,
            'requested_by_id' => $vendorAdmin->id,
            'images' => ['product-requests/test/one.jpg'],
        ]);
    }

    public function test_admin_completes_request_through_product_form_and_requester_is_notified(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = $this->admin(['products.view', 'products.create', 'product_requests.view', 'product_requests.review']);
        $productRequest = $this->vendorRequest();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.create', ['product_request' => $productRequest->id]))
            ->assertOk()
            ->assertSee($productRequest->request_number)
            ->assertSee('name="product_request_id"', false);

        $importedImage = ProductImage::query()->whereNull('product_id')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.products.store'), [
            'name_en' => 'Final Catalog Name',
            'name_ar' => 'الاسم النهائي',
            'category_id' => $productRequest->category_id,
            'status' => 'draft',
            'images' => [$importedImage->id],
            'product_request_id' => $productRequest->id,
        ]);

        $response->assertOk()->assertJsonPath('redirect', route('admin.product-requests.show', $productRequest->id));

        $product = Product::query()->where('name_en', 'Final Catalog Name')->firstOrFail();
        $productRequest->refresh();
        $this->assertSame(ProductRequestStatus::Approved, $productRequest->status);
        $this->assertSame($product->id, $productRequest->product_id);
        $this->assertSame($admin->id, $productRequest->reviewed_by);
        $this->assertSame($product->id, $importedImage->fresh()->product_id);

        Notification::assertSentTo(
            $productRequest->requestedBy,
            ProductRequestApproved::class,
            fn (ProductRequestApproved $notification, array $channels) => in_array('mail', $channels, true)
                && str_contains($notification->notificationData($productRequest->requestedBy)['message_en'], 'Final Catalog Name'),
        );
    }

    public function test_store_rejects_a_request_that_is_no_longer_pending(): void
    {
        $admin = $this->admin(['products.view', 'products.create', 'product_requests.review']);
        $productRequest = ProductRequest::factory()->rejected()->create();

        $this->actingAs($admin, 'admin')->postJson(route('admin.products.store'), [
            'name_en' => 'Anything',
            'name_ar' => 'أي شيء',
            'category_id' => Category::factory()->create()->id,
            'status' => 'draft',
            'product_request_id' => $productRequest->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_request_id');

        $this->assertFalse(Product::query()->where('name_en', 'Anything')->exists());
    }

    public function test_admin_rejects_request_with_reason(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = $this->admin(['product_requests.view', 'product_requests.review']);
        $productRequest = $this->vendorRequest();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'short'])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'Duplicate of an existing catalog product.'])
            ->assertRedirect(route('admin.product-requests.show', $productRequest->id));

        $this->assertSame(ProductRequestStatus::Rejected, $productRequest->fresh()->status);
        Notification::assertSentTo($productRequest->requestedBy, ProductRequestRejected::class);
    }

    public function test_index_and_show_render_for_viewers(): void
    {
        $admin = $this->admin(['product_requests.view']);
        $productRequest = ProductRequest::factory()->marketer()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.product-requests.index'))
            ->assertOk()
            ->assertSee($productRequest->request_number);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.product-requests.show', $productRequest->id))
            ->assertOk()
            ->assertSee($productRequest->name_en)
            ->assertDontSee(route('admin.product-requests.approve', $productRequest->id));
    }
}
