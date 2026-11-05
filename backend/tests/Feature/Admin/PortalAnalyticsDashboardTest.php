<?php

namespace Tests\Feature\Admin;

use App\Enums\RegistrationFlow;
use App\Models\Admin;
use App\Models\Country;
use App\Models\PortalPageView;
use App\Models\PortalVisitor;
use App\Models\RegistrationProgress;
use App\Support\PortalPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The admin portal-analytics page: permission, real aggregates, filters, comparison and export.
 * Data is written straight to the tracking tables so every number can be asserted exactly.
 */
class PortalAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PortalPermissions::ensureExist();
        foreach (['dashboard.view', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $this->withSession(['locale' => 'en']);
        $this->admin = $this->adminWith([PortalPermissions::ANALYTICS_VIEW, 'dashboard.view']);

        Country::factory()->create(['site_code' => 'omn', 'iso_code_2' => 'OM', 'name_en' => 'Oman', 'is_active' => true]);
        Country::factory()->create(['site_code' => 'kwt', 'iso_code_2' => 'KW', 'name_en' => 'Kuwait', 'is_active' => true]);
    }

    /** @param list<string> $permissions */
    private function adminWith(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $role = Role::create(['name' => 'role_'.Str::random(8), 'guard_name' => 'admin']);
        $role->givePermissionTo($permissions);
        $admin->assignRole($role);

        return $admin;
    }

    private function visitor(string $firstSeen, string $country = 'omn'): PortalVisitor
    {
        return PortalVisitor::create([
            'id' => (string) Str::uuid(),
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $firstSeen,
            'country_code' => $country,
        ]);
    }

    private function pageView(PortalVisitor $visitor, string $at, string $path = '/faq', string $country = 'omn', bool $newVisit = false): void
    {
        PortalPageView::create([
            'visitor_id' => $visitor->id,
            'path' => $path,
            'country_code' => $country,
            'is_new_visit' => $newVisit,
            'viewed_at' => $at,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $events  list of [event, step]
     */
    private function registration(PortalVisitor $visitor, string $startedAt, array $events, string $country = 'omn', bool $completed = false, string $flow = 'vendor'): RegistrationProgress
    {
        $progress = RegistrationProgress::create([
            'visitor_id' => $visitor->id,
            'flow' => $flow,
            'country_code' => $country,
            'started_at' => $startedAt,
            'first_submitted_at' => collect($events)->contains(fn ($e) => in_array($e[0], ['completed', 'validation_failed'], true)) ? $startedAt : null,
            'last_activity_at' => $startedAt,
            'completed_at' => $completed ? $startedAt : null,
        ]);

        foreach ($events as [$event, $step]) {
            DB::table('registration_step_events')->insert([
                'registration_progress_id' => $progress->id, 'step' => $step, 'event' => $event, 'occurrences' => 1, 'occurred_at' => $startedAt,
            ]);
        }

        return $progress;
    }

    private function data(array $query = [])
    {
        return $this->actingAs($this->admin, 'admin')->getJson(route('admin.portal-analytics.data', $query));
    }

    /**
     * Seeds 2025-03-10 .. 2025-03-12 traffic for Oman and Kuwait plus a previous period.
     */
    private function seedTraffic(): void
    {
        $returning = $this->visitor('2025-02-01 09:00:00');
        $newcomer = $this->visitor('2025-03-10 09:00:00');
        $kuwaiti = $this->visitor('2025-03-11 09:00:00', 'kwt');

        $this->pageView($returning, '2025-03-10 09:00:00', '/faq', newVisit: true);
        $this->pageView($returning, '2025-03-10 09:05:00', '/vendors');
        $this->pageView($newcomer, '2025-03-10 10:00:00', '/faq', newVisit: true);
        $this->pageView($newcomer, '2025-03-12 10:00:00', '/faq', newVisit: true);
        $this->pageView($kuwaiti, '2025-03-11 12:00:00', '/faq', 'kwt', true);

        // Previous period (2025-03-07 .. 2025-03-09): two views from the returning visitor only.
        $this->pageView($returning, '2025-03-08 09:00:00', '/faq', newVisit: true);
        $this->pageView($returning, '2025-03-08 09:01:00', '/vendors');
    }

    // ── Authorisation ────────────────────────────────────────────────────

    public function test_employees_without_the_permission_get_403_everywhere(): void
    {
        $outsider = $this->adminWith(['dashboard.view', PortalPermissions::VIEW]);

        foreach ([
            route('admin.portal-analytics.index'),
            route('admin.portal-analytics.data'),
            route('admin.portal-analytics.index', ['export' => 'csv']),
        ] as $url) {
            $this->actingAs($outsider, 'admin')->get($url)->assertForbidden();
        }

        $this->actingAs($outsider, 'admin')->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee(route('admin.portal-analytics.index'), false);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.portal-analytics.index'))->assertRedirect();
        $this->getJson(route('admin.portal-analytics.data'))->assertUnauthorized();
    }

    public function test_permitted_employee_sees_the_page_and_the_sidebar_link(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index'))
            ->assertOk()
            ->assertSee(__('admin.portal_analytics.title'))
            ->assertSee('Oman')
            ->assertSee('window.PORTAL_ANALYTICS', false)
            ->assertSee('pa-funnel-body', false);

        $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
            ->assertSee(route('admin.portal-analytics.index'), false);
    }

    // ── Aggregates ────────────────────────────────────────────────────────

    public function test_kpis_are_computed_from_tracked_activity(): void
    {
        $this->seedTraffic();

        $kpis = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12'])->assertOk()->json('data.kpis');

        $this->assertSame(5, $kpis['page_views']['value']);
        $this->assertSame(4, $kpis['visits']['value']);
        $this->assertSame(3, $kpis['unique_visitors']['value']);
        $this->assertSame(1, $kpis['returning_visitors']['value'], 'only the visitor first seen before the period is returning');
    }

    public function test_empty_period_reports_zero_and_no_data(): void
    {
        $response = $this->data(['date_from' => '2020-01-01', 'date_to' => '2020-01-31'])->assertOk();

        $this->assertFalse($response->json('data.has_data'));
        $this->assertSame(0, $response->json('data.kpis.page_views.value'));
        $this->assertSame(0.0, (float) $response->json('data.kpis.completion_rate.value'));
        $this->assertCount(5, $response->json('data.funnel.steps'));
    }

    public function test_date_range_filter_limits_every_number(): void
    {
        $this->seedTraffic();

        $oneDay = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-10', 'compare' => 0])->json('data');

        $this->assertSame(3, $oneDay['kpis']['page_views']['value']);
        $this->assertSame(2, $oneDay['kpis']['unique_visitors']['value']);
        $this->assertSame(['2025-03-10'], $oneDay['timeline']['labels']);
        $this->assertSame([3], $oneDay['timeline']['page_views']);
        $this->assertSame(['/faq', '/vendors'], array_column($oneDay['top_pages'], 'path'));
        $this->assertSame([2, 1], array_column($oneDay['top_pages'], 'views'));
    }

    public function test_country_filter_limits_traffic_and_registrations(): void
    {
        $this->seedTraffic();
        $kuwaiti = PortalVisitor::where('country_code', 'kwt')->first();
        $this->registration($kuwaiti, '2025-03-11 12:00:00', [['viewed', 1]], 'kwt');
        $omani = PortalVisitor::where('country_code', 'omn')->first();
        $this->registration($omani, '2025-03-10 12:00:00', [['viewed', 1], ['completed', 1], ['viewed', 2]], 'omn');

        $kuwait = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12', 'country' => 'kwt'])->json('data');
        $this->assertSame(1, $kuwait['kpis']['page_views']['value']);
        $this->assertSame(1, $kuwait['kpis']['registration_starts']['value']);
        $this->assertSame(1, $kuwait['funnel']['steps'][0]['reached']);
        $this->assertSame(0, $kuwait['funnel']['steps'][0]['completed']);

        $oman = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12', 'country' => 'omn'])->json('data');
        $this->assertSame(4, $oman['kpis']['page_views']['value']);
        $this->assertSame(1, $oman['funnel']['steps'][0]['completed']);

        $all = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12'])->json('data');
        $this->assertSame(2, $all['kpis']['registration_starts']['value']);
    }

    public function test_funnel_shows_reached_completed_abandoned_conversion_and_dropoff_per_step(): void
    {
        // Four visitors start. Two finish step 1; one of those finishes step 2 and then gives up on step 3.
        $a = $this->visitor('2025-03-10 08:00:00');
        $b = $this->visitor('2025-03-10 08:00:00');
        $c = $this->visitor('2025-03-10 08:00:00');
        $d = $this->visitor('2025-03-10 08:00:00');

        $this->registration($a, '2025-03-10 09:00:00', [['viewed', 1]]);
        $this->registration($b, '2025-03-10 09:00:00', [['viewed', 1], ['validation_failed', 1]]);
        $this->registration($c, '2025-03-10 09:00:00', [['viewed', 1], ['completed', 1], ['viewed', 2]]);
        $this->registration($d, '2025-03-10 09:00:00', [['viewed', 1], ['completed', 1], ['viewed', 2], ['completed', 2], ['viewed', 3]]);

        $funnel = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-10'])->json('data.funnel');
        [$s1, $s2, $s3] = $funnel['steps'];

        $this->assertSame(['account', 'store', 'contact', 'documents', 'review'], array_column($funnel['steps'], 'key'));
        $this->assertSame(4, $funnel['started']);

        $this->assertEquals([4, 2, 2, 50.0, 50.0], [$s1['reached'], $s1['completed'], $s1['abandoned'], $s1['conversion_rate'], $s1['dropoff_rate']]);
        $this->assertSame(1, $s1['failed_registrations']);
        $this->assertEquals([2, 1, 1, 50.0, 50.0], [$s2['reached'], $s2['completed'], $s2['abandoned'], $s2['conversion_rate'], $s2['dropoff_rate']]);
        $this->assertEquals(50.0, $s2['reached_of_start']);
        $this->assertEquals([1, 0, 1, 0.0, 100.0], [$s3['reached'], $s3['completed'], $s3['abandoned'], $s3['conversion_rate'], $s3['dropoff_rate']]);
        $this->assertSame(0, $funnel['completed']);
        $this->assertSame('Account details', $s1['label']);
    }

    public function test_completed_registrations_and_completion_rate(): void
    {
        $steps = fn (int $upTo) => collect(range(1, $upTo))->flatMap(fn ($s) => [['viewed', $s], ['completed', $s]])->all();

        $this->registration($this->visitor('2025-03-10 08:00:00'), '2025-03-10 09:00:00', $steps(5), completed: true);
        $this->registration($this->visitor('2025-03-10 08:00:00'), '2025-03-10 09:00:00', $steps(2));
        $this->registration($this->visitor('2025-03-10 08:00:00'), '2025-03-10 09:00:00', [['viewed', 1]]);
        $this->registration($this->visitor('2025-03-10 08:00:00'), '2025-03-10 09:00:00', $steps(3), flow: 'travel_agency');

        $data = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-10'])->json('data');

        $this->assertSame(3, $data['kpis']['registration_starts']['value'], 'only the selected flow is counted');
        $this->assertSame(2, $data['kpis']['registration_attempts']['value']);
        $this->assertSame(1, $data['kpis']['registrations_completed']['value']);
        $this->assertSame(33.3, (float) $data['kpis']['completion_rate']['value']);
        $this->assertSame(1, $data['funnel']['completed']);
        $this->assertSame([1], array_slice($data['timeline']['registrations_completed'], 0, 1));

        $agency = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-10', 'flow' => RegistrationFlow::TravelAgency->value])->json('data');
        $this->assertSame(['account', 'agency', 'review'], array_column($agency['funnel']['steps'], 'key'));
        $this->assertSame(1, $agency['kpis']['registration_starts']['value']);
    }

    public function test_comparison_uses_the_previous_period_of_equal_length_and_computes_deltas(): void
    {
        $this->seedTraffic();

        $data = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12'])->assertOk()->json('data');

        $this->assertSame('2025-03-07', $data['period']['previous_from']);
        $this->assertSame('2025-03-09', $data['period']['previous_to']);

        $views = $data['kpis']['page_views'];
        $this->assertEquals([5, 2, 150.0], [$views['value'], $views['previous'], $views['change']]);

        $uniques = $data['kpis']['unique_visitors'];
        $this->assertEquals([3, 1, 200.0], [$uniques['value'], $uniques['previous'], $uniques['change']]);

        // The returning visitor already existed before the previous period too.
        $this->assertSame(1, $data['kpis']['returning_visitors']['previous']);
        $this->assertSame(0.0, (float) $data['kpis']['returning_visitors']['change']);
    }

    public function test_comparison_can_be_switched_off_and_has_no_delta_without_a_baseline(): void
    {
        $this->seedTraffic();

        $off = $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-12', 'compare' => 0])->json('data');
        $this->assertNull($off['period']['previous_from']);
        $this->assertNull($off['kpis']['page_views']['previous']);
        $this->assertNull($off['kpis']['page_views']['change']);

        // 2025-03-20..22 is compared with the empty 2025-03-17..19: no percentage can be computed.
        $this->pageView(PortalVisitor::first(), '2025-03-21 09:00:00', '/faq');
        $noBaseline = $this->data(['date_from' => '2025-03-20', 'date_to' => '2025-03-22'])->json('data');
        $this->assertSame(1, $noBaseline['kpis']['page_views']['value']);
        $this->assertSame(0, $noBaseline['kpis']['page_views']['previous']);
        $this->assertNull($noBaseline['kpis']['page_views']['change']);
    }

    public function test_weekly_and_monthly_granularity_group_the_timeline(): void
    {
        $visitor = $this->visitor('2025-01-01 08:00:00');
        $this->pageView($visitor, '2025-03-03 09:00:00', '/a');   // Monday
        $this->pageView($visitor, '2025-03-09 09:00:00', '/b');   // Sunday, same ISO week
        $this->pageView($visitor, '2025-03-10 09:00:00', '/c');   // next Monday
        $this->pageView($visitor, '2025-04-02 09:00:00', '/d');

        $weekly = $this->data(['date_from' => '2025-03-03', 'date_to' => '2025-03-16', 'granularity' => 'week', 'compare' => 0])->json('data.timeline');
        $this->assertSame('week', $weekly['granularity']);
        $this->assertSame(['2025-03-03', '2025-03-10'], $weekly['labels']);
        $this->assertSame([2, 1], $weekly['page_views']);

        $monthly = $this->data(['date_from' => '2025-03-01', 'date_to' => '2025-04-30', 'granularity' => 'month', 'compare' => 0])->json('data.timeline');
        $this->assertSame(['2025-03-01', '2025-04-01'], $monthly['labels']);
        $this->assertSame([3, 1], $monthly['page_views']);

        $daily = $this->data(['date_from' => '2025-03-03', 'date_to' => '2025-03-05', 'granularity' => 'day', 'compare' => 0])->json('data.timeline');
        $this->assertSame(['2025-03-03', '2025-03-04', '2025-03-05'], $daily['labels']);
        $this->assertSame([1, 0, 0], $daily['page_views']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->data(['date_from' => '2025-03-10', 'date_to' => '2025-03-01'])->assertStatus(422)->assertJsonValidationErrors('date_to');
        $this->data(['date_from' => 'yesterday'])->assertStatus(422)->assertJsonValidationErrors('date_from');
        $this->data(['granularity' => 'hour'])->assertStatus(422)->assertJsonValidationErrors('granularity');
        $this->data(['flow' => 'unknown'])->assertStatus(422)->assertJsonValidationErrors('flow');
        $this->data(['country' => 'zzz'])->assertStatus(422)->assertJsonValidationErrors('country');
        $this->data(['date_from' => '2020-01-01', 'date_to' => '2025-01-01'])->assertStatus(422)->assertJsonValidationErrors('date_to');
    }

    // ── Export ────────────────────────────────────────────────────────────

    public function test_summary_funnel_and_timeline_can_be_exported_as_csv(): void
    {
        $this->seedTraffic();
        $this->registration(PortalVisitor::first(), '2025-03-10 12:00:00', [['viewed', 1], ['completed', 1], ['viewed', 2]]);
        $query = ['date_from' => '2025-03-10', 'date_to' => '2025-03-12', 'export' => 'csv'];

        $summary = $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $query));
        $summary->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $summary->streamedContent();
        $this->assertStringContainsString('"'.__('admin.portal_analytics.kpi.page_views').'",5,2,150', $csv);
        $this->assertStringContainsString('"'.__('admin.portal_analytics.kpi.unique_visitors').'",3,1,200', $csv);

        $funnel = $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $query + ['section' => 'funnel']))->streamedContent();
        $this->assertStringContainsString('"1. Account details",1,1,0,100,0', $funnel);
        $this->assertStringContainsString('"2. Store details",1,0,1,0,100', $funnel);

        $timeline = $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $query + ['section' => 'timeline']))->streamedContent();
        $this->assertStringContainsString('2025-03-10,3,', $timeline);

        $pages = $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $query + ['section' => 'top_pages']))->streamedContent();
        $this->assertStringContainsString('/faq,4,3', $pages);
    }

    public function test_export_supports_excel_and_word_and_rejects_unknown_formats(): void
    {
        $this->seedTraffic();
        $base = ['date_from' => '2025-03-10', 'date_to' => '2025-03-12'];

        $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $base + ['export' => 'excel']))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="portal-analytics-summary-2025-03-10-2025-03-12.xlsx"');
        $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $base + ['export' => 'word']))->assertOk();
        $this->actingAs($this->admin, 'admin')->get(route('admin.portal-analytics.index', $base + ['export' => 'pdf']))->assertSessionHasErrors('export');
    }
}
