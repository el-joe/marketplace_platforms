<?php

namespace Tests\Feature\ProductRequests;

use App\Enums\ProductRequestStatus;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Notifications\Admin\NewProductRequestSubmitted;
use App\Notifications\ProductRequestApproved;
use App\Notifications\ProductRequestRejected;
use App\Services\Catalog\ProductRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Review lifecycle of product requests: authorization, approval/rejection side effects,
 * notifications, image import housekeeping, admin queue filters and translations.
 */
class ProductRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEWER_PERMISSIONS = ['products.view', 'products.create', 'product_requests.view', 'product_requests.review'];

    /**
     * @param  array<int, string>  $permissions
     */
    private function admin(array $permissions, string $status = 'active'): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create(['status' => $status]);
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function vendorAdmin(Vendor $vendor, bool $isActive = true): VendorAdmin
    {
        return VendorAdmin::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Vendor User',
            'email' => 'v-'.Str::random(10).'@example.test',
            'password' => Hash::make('password'),
            'is_active' => $isActive,
        ]);
    }

    private function marketerAdmin(Marketer $marketer): MarketerAdmin
    {
        return MarketerAdmin::query()->create([
            'marketer_id' => $marketer->id,
            'name' => 'Marketer User',
            'email' => 'm-'.Str::random(10).'@example.test',
            'password' => Hash::make('password'),
            'is_owner' => true,
            'is_active' => true,
        ]);
    }

    /**
     * A pending vendor request submitted by a team member, with real image files on the fake disk.
     */
    private function vendorRequest(int $imageCount = 1, ?VendorAdmin $submitter = null): ProductRequest
    {
        $submitter ??= $this->vendorAdmin(Vendor::factory()->create());

        $images = [];
        for ($i = 1; $i <= $imageCount; $i++) {
            $images[] = $path = "product-requests/source/image-{$i}.jpg";
            Storage::disk('public')->put($path, UploadedFile::fake()->image("image-{$i}.jpg")->getContent());
        }

        return ProductRequest::factory()->vendor($submitter->vendor)->create([
            'requested_by_type' => VendorAdmin::class,
            'requested_by_id' => $submitter->id,
            'images' => $images,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(ProductRequest $productRequest, array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Catalog Product',
            'name_ar' => 'منتج الكتالوج',
            'category_id' => $productRequest->category_id,
            'status' => 'draft',
            'product_request_id' => $productRequest->id,
        ], $overrides);
    }

    public function test_viewer_without_review_permission_is_forbidden_from_rejecting_or_approving(): void
    {
        $viewer = $this->admin(['products.view', 'products.create', 'product_requests.view']);
        $productRequest = ProductRequest::factory()->create();

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'Not a real product at all.'])
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->get(route('admin.product-requests.approve', $productRequest->id))
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->get(route('admin.products.create', ['product_request' => $productRequest->id]))
            ->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->postJson(route('admin.products.store'), $this->productPayload($productRequest))
            ->assertForbidden();

        $this->assertSame(ProductRequestStatus::Pending, $productRequest->fresh()->status);
        $this->assertFalse(Product::query()->where('name_en', 'Catalog Product')->exists());
    }

    public function test_reviewer_without_products_create_cannot_open_the_approve_form(): void
    {
        $reviewer = $this->admin(['product_requests.view', 'product_requests.review']);
        $productRequest = ProductRequest::factory()->create();

        $this->actingAs($reviewer, 'admin')
            ->get(route('admin.products.create', ['product_request' => $productRequest->id]))
            ->assertForbidden();
    }

    public function test_approval_notifies_submitter_by_mail_and_database_with_the_final_product_name_and_discards_unused_image_copies(): void
    {
        Storage::fake('public');
        Notification::fake();
        Cache::put(ProductRequestService::NAV_BADGE_CACHE_KEY, 9);
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $productRequest = $this->vendorRequest(imageCount: 2);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.create', ['product_request' => $productRequest->id]))
            ->assertOk();
        [$keptImage, $droppedImage] = ProductImage::query()->whereNull('product_id')->orderBy('path')->get()->all();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.products.store'), $this->productPayload($productRequest, [
                'name_en' => 'Renamed By Admin',
                'name_ar' => 'اسم معدل',
                'images' => [$keptImage->id],
            ]))
            ->assertOk();

        $product = Product::query()->where('name_en', 'Renamed By Admin')->sole();
        $this->assertSame($product->id, $productRequest->fresh()->product_id);
        $this->assertSame($product->id, $keptImage->fresh()->product_id);
        $this->assertNull($droppedImage->fresh());
        Storage::disk('public')->assertMissing($droppedImage->path);
        Storage::disk('public')->assertExists($keptImage->path);
        $this->assertFalse(Cache::has(ProductRequestService::NAV_BADGE_CACHE_KEY));

        $submitter = $productRequest->requestedBy;
        Notification::assertSentTo(
            $submitter,
            ProductRequestApproved::class,
            function (ProductRequestApproved $notification, array $channels) use ($submitter, $product): bool {
                $data = $notification->toDatabase($submitter);

                return $channels === ['database', 'broadcast', 'mail']
                    && $notification->product->is($product)
                    && str_contains($data['message_en'], 'Renamed By Admin')
                    && str_contains($data['message_ar'], 'اسم معدل')
                    && ! str_contains($data['message_en'], $notification->productRequest->name_en)
                    && $data['url'] === route('partner.product-requests.show', $notification->productRequest->id);
            },
        );
    }

    public function test_rejection_notifies_submitter_in_database_only_with_the_reason(): void
    {
        Notification::fake();
        Cache::put(ProductRequestService::NAV_BADGE_CACHE_KEY, 9);
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $submitter = $this->vendorAdmin(Vendor::factory()->create());
        $productRequest = ProductRequest::factory()->vendor($submitter->vendor)->create([
            'requested_by_type' => VendorAdmin::class,
            'requested_by_id' => $submitter->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'Please attach the real packaging photos.'])
            ->assertSessionHas('success');

        $productRequest->refresh();
        $this->assertSame(ProductRequestStatus::Rejected, $productRequest->status);
        $this->assertSame('Please attach the real packaging photos.', $productRequest->rejection_reason);
        $this->assertSame($admin->id, $productRequest->reviewed_by);
        $this->assertFalse(Cache::has(ProductRequestService::NAV_BADGE_CACHE_KEY));

        Notification::assertSentTo(
            $submitter,
            ProductRequestRejected::class,
            fn (ProductRequestRejected $notification, array $channels): bool => ! in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && $notification->toDatabase($submitter)['rejection_reason'] === 'Please attach the real packaging photos.'
                && str_contains($notification->toDatabase($submitter)['message_en'], 'Please attach the real packaging photos.'),
        );
    }

    public function test_second_rejection_of_the_same_request_is_refused_without_a_second_notification(): void
    {
        Notification::fake();
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $productRequest = ProductRequest::factory()->rejected('The first and final rejection reason.')->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'A different reason given later.'])
            ->assertRedirect(route('admin.product-requests.show', $productRequest->id))
            ->assertSessionHas('error', __('product_requests.messages.not_pending'));

        $this->assertSame('The first and final rejection reason.', $productRequest->fresh()->rejection_reason);
        Notification::assertNothingSent();
    }

    public function test_requester_notifications_are_stored_in_the_panel_notifications_table(): void
    {
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $submitter = $this->vendorAdmin(Vendor::factory()->create());
        $productRequest = ProductRequest::factory()->vendor($submitter->vendor)->create([
            'requested_by_type' => VendorAdmin::class,
            'requested_by_id' => $submitter->id,
        ]);

        app(ProductRequestService::class)->reject($productRequest, $admin, 'Duplicate of an existing listing.');

        $row = DB::table('notifications')
            ->where('notifiable_type', VendorAdmin::class)
            ->where('notifiable_id', $submitter->id)
            ->sole();
        $data = json_decode($row->data, true);

        $this->assertSame(ProductRequestRejected::class, $row->type);
        $this->assertSame('database', $row->channel);
        $this->assertSame('product_request_rejected', $data['type']);
        $this->assertNotEmpty($data['title']);
        $this->assertStringContainsString('Duplicate of an existing listing.', $data['message']);
        $this->assertSame(route('partner.product-requests.show', $productRequest->id), $data['url']);
    }

    public function test_requester_notification_falls_back_to_active_team_when_submitter_is_inactive_or_deleted(): void
    {
        Notification::fake();
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $vendor = Vendor::factory()->create();
        $inactiveSubmitter = $this->vendorAdmin($vendor, isActive: false);
        $deletedSubmitter = $this->vendorAdmin($vendor);
        $activeTeammate = $this->vendorAdmin($vendor);
        $otherVendorMember = $this->vendorAdmin(Vendor::factory()->create());

        $fromInactive = ProductRequest::factory()->vendor($vendor)->create(['requested_by_type' => VendorAdmin::class, 'requested_by_id' => $inactiveSubmitter->id]);
        $fromDeleted = ProductRequest::factory()->vendor($vendor)->create(['requested_by_type' => VendorAdmin::class, 'requested_by_id' => $deletedSubmitter->id]);
        $deletedSubmitter->delete();

        $service = app(ProductRequestService::class);
        $service->reject($fromInactive, $admin, 'Not enough product information.');
        $service->reject($fromDeleted, $admin, 'Not enough product information.');

        Notification::assertSentToTimes($activeTeammate, ProductRequestRejected::class, 2);
        Notification::assertNotSentTo([$inactiveSubmitter, $otherVendorMember], ProductRequestRejected::class);
    }

    public function test_marking_a_request_approved_after_it_left_pending_throws_and_notifies_nobody(): void
    {
        Notification::fake();
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $cancelled = ProductRequest::factory()->create(['status' => ProductRequestStatus::Cancelled]);
        $product = Product::factory()->create();

        try {
            DB::transaction(fn () => app(ProductRequestService::class)->markApproved($cancelled, $product, $admin));
            $this->fail('markApproved() accepted a cancelled request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_request_id', $exception->errors());
        }

        $this->assertSame(ProductRequestStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertNull($cancelled->fresh()->product_id);
        Notification::assertNothingSent();
    }

    public function test_approval_rolled_back_with_the_product_transaction_sends_no_notification(): void
    {
        Notification::fake();
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $productRequest = $this->vendorRequest(imageCount: 0);
        $product = Product::factory()->create();

        try {
            DB::transaction(function () use ($productRequest, $product, $admin): void {
                app(ProductRequestService::class)->markApproved($productRequest, $product, $admin);

                throw new \RuntimeException('Product save failed later in the transaction.');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(ProductRequestStatus::Pending, $productRequest->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_submission_notifies_only_active_reviewers_and_drops_brand_name_when_a_brand_is_chosen(): void
    {
        Storage::fake('public');
        Notification::fake();
        $reviewer = $this->admin(['product_requests.view', 'product_requests.review']);
        $viewerOnly = $this->admin(['product_requests.view']);
        $inactiveReviewer = $this->admin(['product_requests.review'], status: 'inactive');
        $brand = Brand::query()->create(['name_en' => 'Acme', 'name_ar' => 'أكمي', 'slug' => 'acme-'.Str::random(5), 'is_active' => true]);
        $submitter = $this->marketerAdmin(Marketer::factory()->create());

        $productRequest = app(ProductRequestService::class)->submit([
            'name_en' => 'Acme Kettle',
            'name_ar' => 'غلاية أكمي',
            'category_id' => Category::factory()->create()->id,
            'brand_id' => $brand->id,
            'brand_name' => 'Ignored Brand Text',
            'description' => 'An electric kettle with a 1.7 litre capacity and auto shut-off.',
        ], $submitter, [UploadedFile::fake()->image('kettle.jpg')]);

        $this->assertSame($brand->id, $productRequest->brand_id);
        $this->assertNull($productRequest->brand_name);
        $this->assertSame(ProductRequest::REQUESTER_MARKETER, $productRequest->requester_type);
        $this->assertSame($submitter->marketer_id, $productRequest->marketer_id);
        Storage::disk('public')->assertExists($productRequest->images[0]);

        Notification::assertSentTo(
            $reviewer,
            NewProductRequestSubmitted::class,
            fn (NewProductRequestSubmitted $notification): bool => $notification->productRequest->is($productRequest)
                && $notification->toDatabase($reviewer)['url'] === route('admin.product-requests.show', $productRequest->id),
        );
        Notification::assertNotSentTo([$viewerOnly, $inactiveReviewer], NewProductRequestSubmitted::class);
    }

    public function test_reopening_the_approve_form_reuses_imported_images_and_rejection_removes_them(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $productRequest = $this->vendorRequest(imageCount: 2);
        $importDirectory = ProductRequestService::importedImageDirectory($productRequest);

        $this->actingAs($admin, 'admin')->get(route('admin.products.create', ['product_request' => $productRequest->id]))->assertOk();
        $firstImportIds = ProductImage::query()->whereNull('product_id')->pluck('id')->sort()->values();

        $this->actingAs($admin, 'admin')->get(route('admin.products.create', ['product_request' => $productRequest->id]))->assertOk();

        $this->assertCount(2, $firstImportIds);
        $this->assertEquals($firstImportIds, ProductImage::query()->whereNull('product_id')->pluck('id')->sort()->values());
        $this->assertCount(2, Storage::disk('public')->files($importDirectory));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.product-requests.reject', $productRequest->id), ['rejection_reason' => 'Product is not allowed on the marketplace.']);

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame([], Storage::disk('public')->files($importDirectory));
        foreach ($productRequest->images as $sourcePath) {
            Storage::disk('public')->assertExists($sourcePath);
        }
    }

    public function test_admin_index_filters_by_status_requester_search_and_date(): void
    {
        $admin = $this->admin(['product_requests.view']);
        $vendorPending = ProductRequest::factory()->vendor()->create(['name_en' => 'Vendor Pending Item', 'gtin' => '1111111111116']);
        $marketerPending = ProductRequest::factory()->marketer()->create(['name_en' => 'Marketer Pending Item']);
        $rejected = ProductRequest::factory()->rejected()->create(['name_en' => 'Rejected Item']);
        $oldPending = ProductRequest::factory()->create(['name_en' => 'Old Pending Item']);
        $oldPending->forceFill(['created_at' => now()->subDays(20)])->save();

        $index = fn (array $query) => $this->actingAs($admin, 'admin')->get(route('admin.product-requests.index', $query))->assertOk();

        $index([])
            ->assertSee($vendorPending->request_number)
            ->assertSee($oldPending->request_number)
            ->assertDontSee($rejected->request_number);
        $index(['status' => 'all'])->assertSee($rejected->request_number);
        $index(['status' => 'rejected'])
            ->assertSee($rejected->request_number)
            ->assertDontSee($vendorPending->request_number);
        $index(['requester_type' => 'marketer'])
            ->assertSee($marketerPending->request_number)
            ->assertDontSee($vendorPending->request_number);
        $index(['q' => '1111111111116'])
            ->assertSee($vendorPending->request_number)
            ->assertDontSee($marketerPending->request_number);
        $index(['date_from' => now()->subDays(25)->toDateString(), 'date_to' => now()->subDays(15)->toDateString()])
            ->assertSee($oldPending->request_number)
            ->assertDontSee($vendorPending->request_number);
    }

    public function test_show_page_warns_when_the_gtin_entered_the_catalog_and_product_store_refuses_it(): void
    {
        $admin = $this->admin(self::REVIEWER_PERMISSIONS);
        $existing = Product::factory()->create(['gtin' => '6291041500213', 'name_en' => 'Existing Blender']);
        $productRequest = ProductRequest::factory()->create(['gtin' => '6291041500213', 'reference_url' => 'javascript:alert(1)']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.product-requests.show', $productRequest->id))
            ->assertOk()
            ->assertSee(route('admin.products.edit', $existing->id))
            ->assertDontSee('href="javascript:', false);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.products.store'), $this->productPayload($productRequest, ['gtin' => '6291041500213']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gtin');

        $this->assertSame(ProductRequestStatus::Pending, $productRequest->fresh()->status);
    }

    public function test_approved_mail_renders_in_english_and_arabic(): void
    {
        $submitter = $this->vendorAdmin(Vendor::factory()->create());
        $product = Product::factory()->create(['name_en' => 'Mail Product', 'name_ar' => 'منتج البريد']);
        $productRequest = ProductRequest::factory()->vendor($submitter->vendor)->approved()->create(['product_id' => $product->id]);
        $notification = ProductRequestApproved::for($submitter, $productRequest, $product);

        foreach (['en' => 'Mail Product', 'ar' => 'منتج البريد'] as $locale => $productName) {
            app()->setLocale($locale);
            $mail = $notification->toMail($submitter);

            $this->assertStringContainsString($productRequest->request_number, $mail->subject);
            $this->assertStringContainsString($productName, (string) $mail->render());
        }
    }

    public function test_arabic_translations_define_every_product_request_key(): void
    {
        $sections = [
            'product_requests' => null,
            'enums/product_request_status' => null,
            'admin' => 'product_requests',
            'notifications' => 'product_requests',
        ];

        foreach ($sections as $file => $key) {
            $english = require lang_path("en/{$file}.php");
            $arabic = require lang_path("ar/{$file}.php");
            if ($key !== null) {
                $english = $english[$key];
                $arabic = $arabic[$key];
            }

            $englishKeys = array_keys(Arr::dot($english));
            $arabicKeys = array_keys(Arr::dot($arabic));
            sort($englishKeys);
            sort($arabicKeys);

            $this->assertSame(
                $englishKeys,
                $arabicKeys,
                "lang/ar/{$file}.php".($key ? " [{$key}]" : '').' must define the same keys as English.',
            );
        }

        $this->assertNotSame(__('admin.nav.product_requests', [], 'en'), __('admin.nav.product_requests', [], 'ar'));
    }
}
