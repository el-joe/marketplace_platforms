<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\BlockType;
use App\Models\Country;
use App\Models\CustomPage;
use App\Models\File;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageSection;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #27 — shared admin media field: upload + library endpoints and the
 * screens that save what it produces (page-builder blocks/slides, custom page image).
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @param list<string> $permissions */
    private function admin(array $permissions): Admin
    {
        foreach (['vendors.assigned_only', ...$permissions] as $name) {
            Permission::findOrCreate($name, 'admin');
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function pageBuilderAdmin(): Admin
    {
        return $this->admin(['pages.view', 'pages.manage']);
    }

    private function upload(string $context, UploadedFile $image, array $extra = [])
    {
        return $this->postJson(route('admin.media.upload'), ['context' => $context, 'image' => $image, ...$extra]);
    }

    private function block(string $type): PageBlock
    {
        $admin = Admin::factory()->create();
        $page = Page::create([
            'country_id' => Country::factory()->create()->id, 'page_type' => 'home', 'name' => 'Home',
            'slug' => 'home', 'status' => 'draft', 'version' => 1, 'is_default' => false,
        ]);
        $section = PageSection::create(['page_id' => $page->id, 'name' => 'S', 'position' => 1]);

        return PageBlock::create([
            'page_id' => $page->id, 'section_id' => $section->id, 'block_type' => $type,
            'position' => 1, 'created_by_admin_id' => $admin->id, 'config' => [], 'is_visible' => true,
        ]);
    }

    // ── Upload endpoint ────────────────────────────────────────────────────

    public function test_guest_cannot_upload(): void
    {
        $response = $this->upload('slides', UploadedFile::fake()->image('a.png'));

        $this->assertContains($response->status(), [401, 302]);
        $this->assertSame(0, File::count());
    }

    public function test_admin_without_page_permission_is_forbidden(): void
    {
        $this->actingAs($this->admin(['pages.view']), 'admin');

        $this->upload('slides', UploadedFile::fake()->image('a.png'))->assertForbidden();
        $this->assertSame(0, File::count());
    }

    public function test_upload_permission_follows_the_context(): void
    {
        $this->actingAs($this->admin(['static_pages.view', 'static_pages.edit']), 'admin');

        $this->upload('static-pages', UploadedFile::fake()->image('a.png'))->assertOk();
        $this->upload('slides', UploadedFile::fake()->image('b.png'))->assertForbidden();
        $this->upload('custom-pages', UploadedFile::fake()->image('c.png'))->assertForbidden();
    }

    public function test_slide_upload_is_stored_under_the_page_builder_path_with_a_sanitized_name(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');

        $response = $this->upload('slides', UploadedFile::fake()->image('../Summer SALE!! 2026.png', 1920, 600), ['device' => 'mobile'])
            ->assertOk()
            ->assertJsonPath('width', 1920)
            ->assertJsonPath('height', 600);

        $file = File::findOrFail($response->json('file_id'));
        $this->assertMatchesRegularExpression('#^page-builder/slides/summer-sale-2026-[a-z0-9]{10}\.png$#', $file->path);
        $this->assertSame('slide_mobile', $file->file_type);
        $this->assertSame('public', $file->storage_type);
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame(Storage::disk('public')->url($file->path), $response->json('url'));
        $this->assertSame($response->json('url'), $response->json('data.url'));
    }

    public function test_image_content_with_a_script_extension_is_rejected(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');

        $png = UploadedFile::fake()->createWithContent('shell.php', UploadedFile::fake()->image('x.png')->getContent());
        $this->upload('promo-tiles', $png)->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertSame(0, File::count());
    }

    public function test_upload_rejects_non_images_svg_and_oversized_files(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');

        $this->upload('ad-images', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->upload('ad-images', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->upload('ad-images', UploadedFile::fake()->image('big.jpg')->size(8193))->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->upload('nope', UploadedFile::fake()->image('a.png'))->assertUnprocessable()->assertJsonValidationErrors('context');

        $this->assertSame(0, File::count());
    }

    public function test_validation_errors_are_localized(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');

        $this->withSession(['locale' => 'ar'])->withHeader('Accept-Language', 'ar');
        app()->setLocale('ar');
        $this->upload('ad-images', UploadedFile::fake()->image('big.jpg')->size(8193))
            ->assertJsonValidationErrors(['image' => __('media_library.error_size', ['max' => '8 MB'], 'ar')]);
    }

    // ── Library ────────────────────────────────────────────────────────────

    public function test_library_lists_recent_uploads_of_the_context_only(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');
        $tile = $this->upload('promo-tiles', UploadedFile::fake()->image('tile.png'))->json('file_id');
        $this->upload('section-backgrounds', UploadedFile::fake()->image('bg.png'));

        $this->getJson(route('admin.media.library', ['context' => 'promo-tiles']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $tile)
            ->assertJsonPath('next_page', null);
    }

    public function test_library_requires_the_context_permission(): void
    {
        $this->actingAs($this->admin(['static_pages.view', 'static_pages.edit']), 'admin');

        $this->getJson(route('admin.media.library', ['context' => 'slides']))->assertForbidden();
        $this->getJson(route('admin.media.library', ['context' => 'static-pages']))->assertOk();
    }

    // ── Saved data stays in the existing format ────────────────────────────

    public function test_promo_tile_block_saves_the_uploaded_url_as_before(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');
        $block = $this->block('promo_tiles');
        $url = $this->upload('promo-tiles', UploadedFile::fake()->image('tile.png'))->json('url');

        $this->postJson(route('admin.page-builder.blocks.config', $block), [
            'config' => ['tiles' => [['label_en' => 'Deals', 'image_url_en' => $url, 'image_url_ar' => $url, 'link_url' => '/deals']]],
        ])->assertOk();

        $tile = $block->fresh()->config['tiles'][0];
        $this->assertSame($url, $tile['image_url_en']);
        $this->assertSame($url, $tile['image_url_ar']);
        $this->assertStringContainsString('/page-builder/promo-tiles/', $url);
    }

    public function test_hero_slide_saves_uploaded_file_ids_per_device_and_locale(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin');
        $block = $this->block('hero_slider');
        $desktop = $this->upload('slides', UploadedFile::fake()->image('d.png'), ['device' => 'desktop'])->json();
        $mobile = $this->upload('slides', UploadedFile::fake()->image('m.png'), ['device' => 'mobile'])->json();

        $this->postJson(route('admin.page-builder.slides.save', $block), [
            'desktop_file_id_en' => $desktop['file_id'], 'desktop_file_id_ar' => $desktop['file_id'],
            'mobile_file_id_en' => $mobile['file_id'], 'mobile_file_id_ar' => $mobile['file_id'],
            'is_active' => 1,
        ])->assertOk();

        $slide = $this->getJson(route('admin.page-builder.slides.list', $block))->assertOk()->json('slides.0');
        $this->assertSame($desktop['file_id'], $slide['desktop_file_id_en']);
        $this->assertSame($desktop['file_id'], $slide['desktop_file_id']);
        $this->assertStringEndsWith(File::find($mobile['file_id'])->path, $slide['mobile_url_ar']);
    }

    // ── Screens render the shared field ────────────────────────────────────

    public function test_page_builder_modals_and_drawer_render_the_media_field(): void
    {
        $this->actingAs($this->pageBuilderAdmin(), 'admin')
            ->get(route('admin.page-builder.index'))
            ->assertOk()
            ->assertSee('id="slide-media-field"', false)
            ->assertSee('id="slide-mobile-file-id-ar"', false)
            ->assertSee('id="ad-image-media-field"', false)
            ->assertSee('id="sd-background-image-url-en"', false)
            ->assertDontSee('data-slide-upload', false);
    }

    public function test_promo_tiles_config_form_prefills_saved_urls_in_the_media_field(): void
    {
        BlockType::create(['code' => 'promo_tiles', 'label_en' => 'Promo tiles', 'label_ar' => 'بطاقات', 'group' => 'content', 'icon' => 'photo', 'is_active' => true]);
        $block = $this->block('promo_tiles');
        $block->update(['config' => ['tiles' => [['image_url' => '/storage/page-builder/promo-tiles/legacy.png', 'image_url_ar' => 'https://cdn.example/ar.png']]]]);

        $this->actingAs($this->pageBuilderAdmin(), 'admin')
            ->get(route('admin.page-builder.config-form', ['block_type_code' => 'promo_tiles', 'block_id' => $block->id]))
            ->assertOk()
            ->assertSee('name="tiles[0][image_url_en]" value="/storage/page-builder/promo-tiles/legacy.png"', false)
            ->assertSee('name="tiles[0][image_url_ar]" value="https://cdn.example/ar.png"', false)
            ->assertSee('name="tiles[__IDX__][image_url_en]"', false);
    }

    public function test_custom_page_form_shows_the_current_image(): void
    {
        $this->actingAs($this->customPageAdmin(), 'admin');
        $page = $this->customPage();
        $fileId = $this->upload('custom-pages', UploadedFile::fake()->image('hero.jpg'))->json('file_id');
        $this->updateCustomPage($page, ['image_file_id' => $fileId])->assertOk();

        $this->get(route('admin.custom-pages.edit', $page->id))
            ->assertOk()
            ->assertSee('name="image_file_id" value="'.$fileId.'"', false)
            ->assertSee(basename(File::find($fileId)->path), false);
    }

    // ── Custom page image ──────────────────────────────────────────────────

    private function customPageAdmin(): Admin
    {
        return $this->admin(['categories.view', 'categories.create', 'categories.edit']);
    }

    private function customPage(): CustomPage
    {
        return CustomPage::create(['name_en' => 'Deals', 'name_ar' => 'عروض', 'all_categories' => true, 'is_active' => true]);
    }

    private function updateCustomPage(CustomPage $page, array $extra)
    {
        return $this->putJson(route('admin.custom-pages.update', $page->id), ['name_en' => 'Deals', 'name_ar' => 'عروض', 'all_categories' => 1, ...$extra]);
    }

    public function test_custom_page_uses_an_uploaded_library_image_and_can_remove_it(): void
    {
        $this->actingAs($this->customPageAdmin(), 'admin');
        $page = $this->customPage();
        $fileId = $this->upload('custom-pages', UploadedFile::fake()->image('hero.jpg'))->assertOk()->json('file_id');

        $this->updateCustomPage($page, ['image_file_id' => $fileId])->assertOk();
        $image = $page->fresh()->primaryImage;
        $this->assertSame($fileId, $image->id);
        $this->assertNotNull($page->fresh()->image_url);

        $this->updateCustomPage($page, ['image_file_id' => ''])->assertOk();
        $this->assertNull($page->fresh()->primaryImage);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_custom_page_update_without_the_field_keeps_the_image(): void
    {
        $this->actingAs($this->customPageAdmin(), 'admin');
        $page = $this->customPage();
        $fileId = $this->upload('custom-pages', UploadedFile::fake()->image('hero.jpg'))->json('file_id');
        $this->updateCustomPage($page, ['image_file_id' => $fileId])->assertOk();

        $this->updateCustomPage($page, [])->assertOk();

        $this->assertSame($fileId, $page->fresh()->primaryImage?->id);
    }

    public function test_reusing_another_pages_image_copies_it(): void
    {
        $this->actingAs($this->customPageAdmin(), 'admin');
        [$first, $second] = [$this->customPage(), $this->customPage()];
        $fileId = $this->upload('custom-pages', UploadedFile::fake()->image('hero.jpg'))->json('file_id');
        $this->updateCustomPage($first, ['image_file_id' => $fileId])->assertOk();

        $this->updateCustomPage($second, ['image_file_id' => $fileId])->assertOk();

        $copy = $second->fresh()->primaryImage;
        $this->assertNotSame($fileId, $copy->id);
        $this->assertSame($fileId, $first->fresh()->primaryImage->id);
        Storage::disk('public')->assertExists($copy->path);

        $this->updateCustomPage($second, ['image_file_id' => ''])->assertOk();
        Storage::disk('public')->assertExists(File::find($fileId)->path);
    }

    public function test_custom_page_rejects_files_that_are_not_custom_page_images(): void
    {
        $this->actingAs($this->admin(['categories.view', 'categories.edit', 'pages.manage']), 'admin');
        $page = $this->customPage();
        $slideFileId = $this->upload('slides', UploadedFile::fake()->image('s.png'))->json('file_id');

        $this->updateCustomPage($page, ['image_file_id' => $slideFileId])->assertUnprocessable()->assertJsonValidationErrors('image_file_id');
        $this->assertNull($page->fresh()->primaryImage);
    }

    // ── Static pages (Summernote) ──────────────────────────────────────────

    public function test_static_page_editor_upload_returns_a_url_the_sanitizer_keeps(): void
    {
        $this->actingAs($this->admin(['static_pages.view', 'static_pages.create']), 'admin');

        $url = $this->postJson(route('admin.media.upload', ['context' => 'static-pages']), ['image' => UploadedFile::fake()->image('map.webp')])
            ->assertOk()
            ->json('data.url');

        $this->assertStringContainsString('/static-pages/content/map-', $url);
        $this->assertStringContainsString('src="'.$url.'"', (string) HtmlSanitizer::clean('<p><img src="'.$url.'"></p>'));
        $this->assertSame('static_page_image', File::sole()->file_type);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function articleEditorContexts(): array
    {
        return [
            'blog' => ['blog-posts', 'blog/content/', 'blog_post_image', 'admin.blog.posts.create'],
            'help center' => ['help-center-articles', 'helpcenter/content/', 'help_center_article_image', 'admin.helpcenter.articles.create'],
            'ad support' => ['ad-support-articles', 'adsupport/content/', 'ad_support_article_image', 'admin.adsupport.articles.create'],
        ];
    }

    #[DataProvider('articleEditorContexts')]
    public function test_article_editor_images_are_uploaded_instead_of_embedded(string $context, string $directory, string $fileType, string $createRoute): void
    {
        $this->actingAs($this->admin(['blog.view', 'blog.manage', 'help_center.view', 'help_center.manage', 'adsupport.view', 'adsupport.manage']), 'admin');

        $this->get(route($createRoute))
            ->assertOk()
            ->assertSee('data-upload-url="'.e(route('admin.media.upload', ['context' => $context])).'"', false);

        $url = $this->upload($context, UploadedFile::fake()->image('diagram.png'))->assertOk()->json('data.url');

        $this->assertStringContainsString('/'.$directory.'diagram-', $url);
        $this->assertSame($fileType, File::sole()->file_type);
    }

    #[DataProvider('articleEditorContexts')]
    public function test_article_editor_upload_requires_the_section_write_permission(string $context): void
    {
        $this->actingAs($this->admin(['pages.view']), 'admin');

        $this->upload($context, UploadedFile::fake()->image('a.png'))->assertForbidden();
        $this->assertSame(0, File::count());
    }
}
