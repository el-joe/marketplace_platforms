<?php

namespace Tests\Feature\Marketer;

use App\Models\Banner;
use App\Models\BannerPlacementDefinition;
use App\Models\Country;
use App\Models\Marketer;
use App\Services\BannerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketerBannerTargetingTest extends TestCase
{
    use RefreshDatabase;

    private BannerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BannerService::class);
    }

    private function marketer(?string $gender): Marketer
    {
        return Marketer::factory()->withProfile($gender)->create();
    }

    private function names($banners): array
    {
        return $banners->pluck('name')->sort()->values()->all();
    }

    public function test_new_placements_are_registered_and_accepted_by_the_enum(): void
    {
        foreach (['marketers_list_hero', 'marketers_list_strip', 'marketer_profile_slider'] as $code) {
            $this->assertDatabaseHas('banner_placement_definitions', ['code' => $code, 'is_active' => 1]);
            Banner::factory()->placement($code)->create();
        }

        $this->assertSame([1700, 450], [
            BannerPlacementDefinition::where('code', 'marketers_list_hero')->value('width_px'),
            BannerPlacementDefinition::where('code', 'marketers_list_hero')->value('height_px'),
        ]);
        $this->assertSame(1, Banner::where('placement_code', 'marketer_profile_slider')->count());
    }

    public function test_list_gender_scope_rule(): void
    {
        Banner::factory()->marketerGlobal('marketers_list_hero', 'all')->create(['name' => 'all']);
        Banner::factory()->marketerGlobal('marketers_list_hero', 'male')->create(['name' => 'men']);
        Banner::factory()->marketerGlobal('marketers_list_hero', 'female')->create(['name' => 'women']);

        $this->assertSame(['all', 'men'], $this->names($this->service->activeForMarketerList('marketers_list_hero', 'male', null)));
        $this->assertSame(['all', 'women'], $this->names($this->service->activeForMarketerList('marketers_list_hero', 'female', null)));
        $this->assertSame(['all', 'men', 'women'], $this->names($this->service->activeForMarketerList('marketers_list_hero', null, null)));
    }

    public function test_list_ignores_non_global_banners_and_other_placements(): void
    {
        $m = $this->marketer('male');
        Banner::factory()->forMarketers($m, 'marketers_list_hero')->create(['name' => 'selected-on-list']);
        Banner::factory()->marketerGlobal('marketers_list_strip')->create(['name' => 'strip']);
        Banner::factory()->marketerGlobal('marketers_list_hero')->create(['name' => 'hero']);

        $this->assertSame(['hero'], $this->names($this->service->activeForMarketerList('marketers_list_hero', 'male', null)));
        $this->assertSame(['strip'], $this->names($this->service->activeForMarketerList('marketers_list_strip', 'male', null)));
    }

    public function test_list_orders_by_priority_desc(): void
    {
        Banner::factory()->marketerGlobal()->create(['name' => 'low', 'priority' => 1]);
        Banner::factory()->marketerGlobal()->create(['name' => 'high', 'priority' => 9]);
        Banner::factory()->marketerGlobal()->create(['name' => 'mid', 'priority' => 5]);

        $this->assertSame(['high', 'mid', 'low'], $this->service->activeForMarketerList('marketers_list_hero', null, null)->pluck('name')->all());
    }

    public function test_profile_gets_matching_global_and_selected_banners_only(): void
    {
        $man = $this->marketer('male');
        $woman = $this->marketer('female');
        $unset = $this->marketer(null);

        Banner::factory()->marketerGlobal('marketer_profile_slider', 'all')->create(['name' => 'g-all']);
        Banner::factory()->marketerGlobal('marketer_profile_slider', 'male')->create(['name' => 'g-men']);
        Banner::factory()->marketerGlobal('marketer_profile_slider', 'female')->create(['name' => 'g-women']);
        Banner::factory()->forMarketers([$man, $unset])->create(['name' => 'selected']);
        Banner::factory()->forMarketers($woman, 'marketers_list_hero')->create(['name' => 'wrong-placement']);

        $this->assertSame(['g-all', 'g-men', 'selected'], $this->names($this->service->activeForMarketerProfile($man, null)));
        $this->assertSame(['g-all', 'g-women'], $this->names($this->service->activeForMarketerProfile($woman, null)));
        // Gender-less marketers only receive 'all'-scoped global banners plus their selected ones.
        $this->assertSame(['g-all', 'selected'], $this->names($this->service->activeForMarketerProfile($unset, null)));
    }

    public function test_non_global_banner_never_leaks_to_unselected_marketer(): void
    {
        $selected = $this->marketer('male');
        $other = $this->marketer('male');
        Banner::factory()->forMarketers($selected)->create(['name' => 'private']);

        $this->assertSame(['private'], $this->names($this->service->activeForMarketerProfile($selected, null)));
        $this->assertCount(0, $this->service->activeForMarketerProfile($other, null));
    }

    public function test_status_and_schedule_rules(): void
    {
        $m = $this->marketer('female');
        Banner::factory()->marketerGlobal('marketer_profile_slider')->create(['name' => 'live']);
        Banner::factory()->marketerGlobal('marketer_profile_slider')->inactive()->create(['name' => 'inactive']);
        Banner::factory()->marketerGlobal('marketer_profile_slider')->expired()->create(['name' => 'expired']);
        Banner::factory()->marketerGlobal('marketer_profile_slider')->scheduled()->create(['name' => 'scheduled']);
        Banner::factory()->marketerGlobal('marketers_list_hero')->inactive()->create(['name' => 'list-inactive']);
        Banner::factory()->marketerGlobal('marketers_list_hero')->expired()->create(['name' => 'list-expired']);

        $this->assertSame(['live'], $this->names($this->service->activeForMarketerProfile($m, null)));
        $this->assertCount(0, $this->service->activeForMarketerList('marketers_list_hero', null, null));
    }

    public function test_country_rule(): void
    {
        $ae = Country::factory()->create();
        $sa = Country::factory()->create();
        $m = $this->marketer('male');
        Banner::factory()->marketerGlobal('marketer_profile_slider')->create(['name' => 'everywhere']);
        Banner::factory()->marketerGlobal('marketer_profile_slider')->create(['name' => 'ae-only', 'country_id' => $ae->id]);
        Banner::factory()->marketerGlobal('marketers_list_hero')->create(['name' => 'sa-only', 'country_id' => $sa->id]);

        $this->assertSame(['ae-only', 'everywhere'], $this->names($this->service->activeForMarketerProfile($m, $ae->id)));
        $this->assertSame(['everywhere'], $this->names($this->service->activeForMarketerProfile($m, $sa->id)));
        $this->assertCount(1, $this->service->activeForMarketerList('marketers_list_hero', null, $sa->id));
        $this->assertCount(0, $this->service->activeForMarketerList('marketers_list_hero', null, $ae->id));
    }

    public function test_pivot_rows_cascade_and_pairs_are_unique(): void
    {
        $m = $this->marketer('male');
        $banner = Banner::factory()->forMarketers($m)->create();

        $this->assertTrue($m->banners()->whereKey($banner->id)->exists());
        $this->assertTrue($banner->marketers()->whereKey($m->id)->exists());

        $this->expectException(QueryException::class);
        DB::table('banner_marketer')->insert(['banner_id' => $banner->id, 'marketer_id' => $m->id]);
    }

    public function test_deleting_banner_removes_pivot_rows(): void
    {
        $m = $this->marketer('male');
        $banner = Banner::factory()->forMarketers($m)->create();

        $banner->delete();

        $this->assertDatabaseCount('banner_marketer', 0);
        $this->assertDatabaseHas('marketers', ['id' => $m->id]);
    }

    public function test_profile_gender_is_cast_to_enum_and_nullable(): void
    {
        $this->assertNull($this->marketer(null)->marketerProfile->gender);
        $this->assertSame('female', $this->marketer('female')->marketerProfile->fresh()->gender->value);
    }
}
