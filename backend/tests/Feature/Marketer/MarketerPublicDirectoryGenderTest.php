<?php

namespace Tests\Feature\Marketer;

use App\Models\Banner;
use App\Models\File;
use App\Models\Marketer;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerPublicDirectoryGenderTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        // The scenario's own marketer would pollute the directory counts.
        MarketerProfile::query()->update(['profile_slug' => null]);
    }

    private function url(string $path = 'marketers'): string
    {
        return "/api/public/v1/{$this->s->country->site_code}/{$path}";
    }

    private function mk(string $name, string $type, ?string $gender, int $conversions = 0): Marketer
    {
        $marketer = Marketer::create([
            'name' => $name, 'email' => Str::random(8).'@example.test', 'phone' => '+9715'.fake()->numerify('########'),
            'marketer_type' => $type, 'global_status' => 'active', 'country_id' => $this->s->country->id,
            'approved_at' => now(), 'onboarding_completed_at' => now(),
        ]);
        $job = MarketerJob::firstOrCreate(['key' => $type], ['name_ar' => 'ع-'.$type, 'name_en' => 'En '.$type, 'is_active' => true, 'sort_order' => 1]);
        $job->update(['name_ar' => 'ع-'.$type, 'name_en' => 'En '.$type]);
        MarketerMarketerJob::create(['marketer_id' => $marketer->id, 'marketer_job_id' => $job->id]);
        MarketerProfile::create([
            'marketer_id' => $marketer->id, 'profile_slug' => Str::lower(Str::random(8)),
            'gender' => $gender, 'total_conversions' => $conversions,
        ]);

        return $marketer;
    }

    private function bannerImage(Banner $banner, string $slot, string $path): void
    {
        File::create([
            'key' => $path, 'path' => $path, 'storage_type' => 'public', 'file_type' => 'banner_'.$slot,
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size' => 1,
            'model_type' => Banner::class, 'model_id' => $banner->id,
        ]);
    }

    public function test_gender_filter_is_strict_and_excludes_gender_less_marketers(): void
    {
        $this->mk('Man', 'influencer', 'male');
        $this->mk('Woman', 'influencer', 'female');
        $this->mk('Unset', 'influencer', null);

        $men = $this->getJson($this->url('marketers?gender=male'))->assertOk();
        $this->assertEqualsCanonicalizing(['Man'], collect($men->json('data.items'))->pluck('name')->all());

        $women = $this->getJson($this->url('marketers?gender=women'))->assertOk();
        $this->assertEqualsCanonicalizing(['Woman'], collect($women->json('data.items'))->pluck('name')->all());

        $all = $this->getJson($this->url('marketers'))->assertOk();
        $this->assertCount(3, $all->json('data.items'));
        $this->assertSame(3, $all->json('data.meta.total'));
    }

    public function test_cards_expose_gender_type_and_localized_label(): void
    {
        $this->mk('Man', 'broker', 'male');

        $en = $this->getJson($this->url('marketers'), ['Accept-Language' => 'en'])->assertOk()->json('data.items.0');
        $this->assertSame('male', $en['gender']);
        $this->assertSame('broker', $en['type']);
        $this->assertSame('broker', $en['marketer_type']);
        $this->assertSame('En broker', $en['type_label']);
        $this->assertArrayHasKey('slug', $en);
        $this->assertArrayHasKey('profile_slug', $en);
        $this->assertArrayHasKey('classified_count', $en);

        $ar = $this->getJson($this->url('marketers?locale=ar'))->json('data.items.0');
        $this->assertSame('ع-broker', $ar['type_label']);
    }

    public function test_type_search_and_sort_filters(): void
    {
        $this->mk('Alpha Star', 'influencer', 'male', 5);
        $this->mk('Zed Agent', 'affiliate', 'male', 50);
        $this->mk('Beta Star', 'influencer', 'male', 20);

        $this->assertSame(['Zed Agent'], collect($this->getJson($this->url('marketers?type=affiliate'))->json('data.items'))->pluck('name')->all());
        $this->assertSame(['Beta Star', 'Alpha Star'], collect($this->getJson($this->url('marketers?search=Star'))->json('data.items'))->pluck('name')->all());
        $this->assertSame(['Alpha Star', 'Beta Star', 'Zed Agent'], collect($this->getJson($this->url('marketers?sort=name'))->json('data.items'))->pluck('name')->all());
    }

    public function test_grouped_sections_skip_empty_types_and_respect_gender(): void
    {
        $this->mk('Inf', 'influencer', 'male', 1);
        $this->mk('Aff', 'affiliate', 'male', 1);
        $this->mk('Women broker', 'broker', 'female', 1);

        $data = $this->getJson($this->url('marketers?grouped=1&gender=male'), ['Accept-Language' => 'en'])->assertOk()->json('data');

        $this->assertSame(['sections', 'banners'], array_keys($data));
        $this->assertSame(['hero', 'strip'], array_keys($data['banners']));
        $this->assertSame(['influencer', 'affiliate'], array_column($data['sections'], 'type'));
        $this->assertSame(['type', 'label', 'total', 'items'], array_keys($data['sections'][0]));
        $this->assertSame('En influencer', $data['sections'][0]['label']);
    }

    public function test_grouped_sections_cap_at_twelve_most_popular_first(): void
    {
        foreach (range(1, 14) as $i) {
            $this->mk("Inf {$i}", 'influencer', 'male', $i);
        }

        $section = $this->getJson($this->url('marketers?grouped=1&gender=male'))->assertOk()->json('data.sections.0');

        $this->assertSame(14, $section['total']);
        $this->assertCount(12, $section['items']);
        $this->assertSame('Inf 14', $section['items'][0]['name']);
        $this->assertSame('Inf 3', $section['items'][11]['name']);
        $this->assertSame('influencer', $section['items'][0]['type']);
    }

    public function test_list_banners_follow_gender_locale_images_and_never_go_stale(): void
    {
        $man = $this->mk('Man', 'influencer', 'male');

        $all = Banner::factory()->marketerGlobal('marketers_list_hero', 'all')->create(['title_en' => 'All EN', 'title_ar' => 'الكل', 'priority' => 5, 'cta_url' => '/promo']);
        $this->bannerImage($all, 'desktop_en', 'banners/all-desktop-en.jpg');
        $this->bannerImage($all, 'mobile_ar', 'banners/all-mobile-ar.jpg');
        Banner::factory()->marketerGlobal('marketers_list_hero', 'female')->create(['title_en' => 'Women only']);
        Banner::factory()->marketerGlobal('marketers_list_hero', 'all')->inactive()->create(['title_en' => 'Inactive']);
        Banner::factory()->marketerGlobal('marketers_list_hero', 'all')->expired()->create(['title_en' => 'Expired']);
        Banner::factory()->marketerGlobal('marketers_list_strip', 'male')->create(['title_en' => 'Strip men']);
        Banner::factory()->forMarketers($man, 'marketers_list_hero')->create(['title_en' => 'Selected only', 'cta_url' => null]);

        $en = $this->getJson($this->url('marketers?grouped=1&gender=male'), ['Accept-Language' => 'en'])->assertOk()->json('data.banners');
        $this->assertEqualsCanonicalizing(['All EN', 'Selected only'], array_column($en['hero'], 'title'));
        $this->assertSame(['Strip men'], array_column($en['strip'], 'title'));

        $selected = collect($en['hero'])->firstWhere('title', 'Selected only');
        $this->assertSame('/marketer/'.$man->marketerProfile->profile_slug, $selected['link']); // no CTA → marketer details page
        $this->assertNotContains('Selected only', array_column($this->getJson($this->url('marketers?grouped=1&gender=female'))->json('data.banners.hero'), 'title'));

        $hero = collect($en['hero'])->firstWhere('title', 'All EN');
        $this->assertSame(['id', 'title', 'subtitle', 'cta_label', 'link', 'link_type', 'link_reference_id', 'image_desktop', 'image_mobile', 'priority', 'click_url'], array_keys($hero));
        $this->assertSame('/promo', $hero['link']);
        $this->assertSame(5, $hero['priority']);
        $this->assertStringStartsWith('http', $hero['image_desktop']);
        $this->assertStringEndsWith('banners/all-desktop-en.jpg', $hero['image_desktop']);
        $this->assertStringEndsWith('banners/all-mobile-ar.jpg', $hero['image_mobile']); // falls back to the other locale
        $this->assertStringEndsWith("/banners/{$all->id}/click", $hero['click_url']);
        $this->assertStringContainsString($this->s->country->site_code, $hero['click_url']);

        $ar = $this->getJson($this->url('marketers?grouped=1&gender=male&locale=ar'))->json('data.banners.hero.0');
        $this->assertSame('الكل', $ar['title']);
        $this->assertStringEndsWith('banners/all-desktop-en.jpg', $ar['image_desktop']); // fallback to en slot

        $women = $this->getJson($this->url('marketers?grouped=1&gender=female'), ['Accept-Language' => 'en'])->json('data.banners.hero');
        $this->assertEqualsCanonicalizing(['All EN', 'Women only'], array_column($women, 'title'));

        // Not stale: a banner created (or deactivated) after the first call shows immediately.
        Banner::factory()->marketerGlobal('marketers_list_hero', 'male')->create(['title_en' => 'Fresh']);
        $all->update(['status' => 'inactive']);
        $again = $this->getJson($this->url('marketers?grouped=1&gender=male'), ['Accept-Language' => 'en'])->json('data.banners.hero');
        $this->assertEqualsCanonicalizing(['Selected only', 'Fresh'], array_column($again, 'title'));
    }

    public function test_profile_exposes_gender_and_profile_banners(): void
    {
        $woman = $this->mk('Woman', 'influencer', 'female');
        Banner::factory()->marketerGlobal('marketer_profile_slider', 'female')->create(['title_en' => 'For women', 'priority' => 1]);
        Banner::factory()->marketerGlobal('marketer_profile_slider', 'male')->create(['title_en' => 'For men']);
        Banner::factory()->forMarketers($woman)->create(['title_en' => 'Mine', 'priority' => 9]);
        Banner::factory()->forMarketers($this->mk('Other', 'influencer', 'female'))->create(['title_en' => 'Not mine']);

        $slug = $woman->marketerProfile->profile_slug;
        $data = $this->getJson($this->url("marketers/{$slug}"), ['Accept-Language' => 'en'])->assertOk()->json('data');

        $this->assertSame('female', $data['profile']['gender']);
        $this->assertSame(['Mine', 'For women'], array_column($data['banners'], 'title'));
        $this->assertArrayHasKey('click_url', $data['banners'][0]);

        // Banner changes show up despite the cached profile header.
        Banner::factory()->forMarketers($woman)->create(['title_en' => 'Newest', 'priority' => 20]);
        $again = $this->getJson($this->url("marketers/{$slug}"), ['Accept-Language' => 'en'])->json('data.banners');
        $this->assertSame('Newest', $again[0]['title']);
    }
}
