<?php

namespace Tests\Feature\PageBuilder;

use App\Models\Admin;
use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageSection;
use App\Services\PageBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CopyPageToCountryTest extends TestCase
{
    use RefreshDatabase;

    private Country $uae;

    private Country $oman;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uae = Country::factory()->create(['site_code' => 'uae', 'iso_code_2' => 'AE', 'is_active' => true]);
        $this->oman = Country::factory()->create(['site_code' => 'omn', 'iso_code_2' => 'OM', 'is_active' => true]);
    }

    private function makePage(string $type = 'home', string $slug = 'home'): Page
    {
        $page = Page::create([
            'country_id' => $this->uae->id, 'page_type' => $type, 'name' => 'UAE Home',
            'slug' => $slug, 'status' => 'published', 'version' => 4, 'is_default' => true,
        ]);
        foreach ([1, 2] as $i) {
            $section = PageSection::create(['page_id' => $page->id, 'name' => "S{$i}", 'position' => $i]);
            PageBlock::create([
                'page_id' => $page->id, 'section_id' => $section->id, 'block_type' => 'text_block',
                'position' => 1, 'created_by_admin_id' => Admin::factory()->create()->id, 'config' => ['content_html_en' => "Block {$i}"], 'is_visible' => true,
            ]);
        }

        return $page;
    }

    public function test_copy_creates_draft_with_same_sections_and_blocks_in_target_country(): void
    {
        $admin = Admin::factory()->create();
        $page = $this->makePage();

        $result = app(PageBuilderService::class)->copyPageToCountry($page, $this->oman, $admin);
        $copy = $result['page']->fresh();

        $this->assertSame($this->oman->id, $copy->country_id);
        $this->assertSame('draft', $copy->status->value);
        $this->assertFalse($copy->is_default);
        $this->assertSame(1, $copy->version);
        $this->assertSame('home', $copy->slug);
        $this->assertCount(2, $copy->sections);
        $this->assertCount(2, $copy->blocks);
        $this->assertEqualsCanonicalizing(
            $page->sections()->pluck('name')->all(), $copy->sections()->pluck('name')->all()
        );
        // blocks must belong to the NEW sections, not the source ones
        $this->assertEmpty(array_intersect($page->sections()->pluck('id')->all(), $copy->blocks()->pluck('section_id')->all()));
        // source untouched
        $this->assertSame('published', $page->fresh()->status->value);
        $this->assertCount(2, $page->fresh()->blocks);
    }

    public function test_slug_gets_suffix_when_target_country_already_has_it(): void
    {
        $admin = Admin::factory()->create();
        $page = $this->makePage();
        Page::create(['country_id' => $this->oman->id, 'page_type' => 'home', 'name' => 'x', 'slug' => 'home', 'status' => 'draft', 'version' => 1]);

        $copy = app(PageBuilderService::class)->copyPageToCountry($page, $this->oman, $admin)['page'];

        $this->assertSame('home-2', $copy->slug);
    }

    public function test_same_country_and_vendor_pages_are_rejected(): void
    {
        $admin = Admin::factory()->create();
        $service = app(PageBuilderService::class);

        try {
            $service->copyPageToCountry($this->makePage(), $this->uae, $admin);
            $this->fail('same country should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('country_id', $e->errors());
        }

        $vendorPage = $this->makePage('vendor', 'vendor-x');
        $this->expectException(ValidationException::class);
        $service->copyPageToCountry($vendorPage, $this->oman, $admin);
    }
}
