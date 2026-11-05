<?php

namespace Tests\Feature\Portal;

use App\Models\Country;
use App\Models\PortalPageView;
use App\Models\PortalVisitor;
use App\Models\RegistrationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Anonymous portal visitor tracking: unique visitors, refreshes, bots, privacy, retention and the
 * guarantee that analytics can never break a portal page.
 */
class PortalVisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    private string $cookieName;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->cookieName = config('portal_analytics.cookie.name');
        Country::factory()->create(['site_code' => 'omn', 'iso_code_2' => 'OM', 'is_active' => true, 'is_launched' => true]);
        $this->withHeader('User-Agent', self::BROWSER);
    }

    private function visitorIdFrom($response): string
    {
        $id = $response->getCookie($this->cookieName)?->getValue();
        $this->assertTrue(Str::isUuid((string) $id), 'a visitor cookie should have been issued');

        return $id;
    }

    public function test_first_visit_issues_a_cookie_and_records_visitor_and_page_view(): void
    {
        $response = $this->withHeader('User-Agent', self::PHONE)->get(route('portal.faq').'?utm_source=newsletter&utm_medium=email');
        $response->assertOk();

        $id = $this->visitorIdFrom($response);
        $cookie = $response->getCookie($this->cookieName);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addDays(300)->getTimestamp(), $cookie->getExpiresTime());

        $visitor = PortalVisitor::findOrFail($id);
        $this->assertSame('mobile', $visitor->device_type);
        $this->assertSame('newsletter', $visitor->utm_source);
        $this->assertSame('email', $visitor->utm_medium);
        $this->assertNotNull($visitor->country_code);

        $view = PortalPageView::where('visitor_id', $id)->sole();
        $this->assertSame('/faq', $view->path);
        $this->assertTrue($view->is_new_visit);
        $this->assertSame($visitor->country_code, $view->country_code);
    }

    public function test_no_personal_data_is_stored_only_a_salted_ip_hash_and_referrer_host(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://www.google.com/search?q=secret+term&email=a@b.com'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->get(route('portal.faq'));

        $visitor = PortalVisitor::findOrFail($this->visitorIdFrom($response));

        $this->assertSame('www.google.com', $visitor->referrer_host);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $visitor->ip_hash);
        $this->assertSame(hash_hmac('sha256', '203.0.113.77', hash('sha256', 'portal-analytics|'.config('app.key'))), $visitor->ip_hash);

        $dump = json_encode(PortalVisitor::all()->toArray()).json_encode(PortalPageView::all()->toArray());
        $this->assertStringNotContainsString('203.0.113.77', $dump);
        $this->assertStringNotContainsString('secret', $dump);
        $this->assertStringNotContainsString('a@b.com', $dump);
    }

    public function test_refreshing_the_same_page_with_the_same_cookie_is_not_counted_again(): void
    {
        $id = $this->visitorIdFrom($this->get(route('portal.faq')));

        $this->withCookie($this->cookieName, $id)->get(route('portal.faq'))->assertOk();
        $this->withCookie($this->cookieName, $id)->get(route('portal.faq'))->assertOk();

        $this->assertSame(1, PortalVisitor::count());
        $this->assertSame(1, PortalPageView::count());
    }

    public function test_navigating_to_another_page_counts_a_page_view_but_not_a_new_visit_or_visitor(): void
    {
        $id = $this->visitorIdFrom($this->get(route('portal.faq')));

        $this->withCookie($this->cookieName, $id)->get(route('portal.how-it-works'))->assertOk();

        $this->assertSame(1, PortalVisitor::count());
        $this->assertSame(2, PortalPageView::where('visitor_id', $id)->count());
        $this->assertSame(1, PortalPageView::where('is_new_visit', true)->count());
    }

    public function test_a_returning_visit_after_the_idle_gap_is_a_new_visit_of_the_same_visitor(): void
    {
        $id = $this->visitorIdFrom($this->get(route('portal.faq')));

        $this->travel(config('portal_analytics.visit_gap_minutes') + 5)->minutes();

        // Even the same page counts again once the visit has ended.
        $this->withCookie($this->cookieName, $id)->get(route('portal.faq'))->assertOk();

        $this->assertSame(1, PortalVisitor::count());
        $this->assertSame(2, PortalPageView::count());
        $this->assertSame(2, PortalPageView::where('is_new_visit', true)->count());
    }

    public function test_a_different_cookie_is_a_different_unique_visitor(): void
    {
        $first = $this->visitorIdFrom($this->get(route('portal.faq')));
        $second = $this->visitorIdFrom($this->get(route('portal.faq')));

        $this->assertNotSame($first, $second);
        $this->assertSame(2, PortalVisitor::count());
        $this->assertSame(2, PortalPageView::distinct('visitor_id')->count('visitor_id'));
    }

    public function test_a_malformed_cookie_is_replaced_instead_of_trusted(): void
    {
        $response = $this->withCookie($this->cookieName, '1; DROP TABLE portal_visitors')->get(route('portal.faq'));

        $id = $this->visitorIdFrom($response);
        $this->assertSame(1, PortalVisitor::where('id', $id)->count());
    }

    public function test_bots_and_crawlers_are_not_tracked(): void
    {
        foreach (['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'curl/8.4.0', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0', ''] as $agent) {
            $response = $this->withHeader('User-Agent', $agent)->get(route('portal.faq'));
            $response->assertOk();
            $this->assertNull($response->getCookie($this->cookieName), "no cookie for [{$agent}]");
        }

        $this->assertSame(0, PortalVisitor::count());
        $this->assertSame(0, PortalPageView::count());
    }

    public function test_do_not_track_requests_are_not_tracked(): void
    {
        $response = $this->withHeader('DNT', '1')->get(route('portal.faq'));

        $response->assertOk();
        $this->assertNull($response->getCookie($this->cookieName));
        $this->assertSame(0, PortalVisitor::count());
    }

    public function test_helper_ajax_redirect_and_head_requests_do_not_create_page_views(): void
    {
        // JSON / AJAX request
        $this->getJson(route('portal.faq'))->assertOk();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('portal.faq'));
        // HEAD
        $this->call('HEAD', route('portal.faq'));
        // Redirects (home -> /join) and helper routes
        $this->get(route('portal.home'))->assertRedirect();
        $this->get(route('portal.language', 'en'))->assertRedirect();
        $this->get(route('portal.register.cities', ['country_id' => (string) Str::uuid()]));
        // Browser prefetch
        $this->withHeader('Sec-Purpose', 'prefetch')->get(route('portal.faq'));

        $this->assertSame(0, PortalPageView::count());
    }

    public function test_country_prefixed_pages_are_stored_without_the_country_segment(): void
    {
        $this->get(route('portal.sellers', 'omn'))->assertOk();

        $this->assertSame('/advertise/sellers', PortalPageView::sole()->path);
        $this->assertSame('omn', PortalPageView::sole()->country_code);
    }

    public function test_a_tracking_failure_never_breaks_the_page(): void
    {
        Log::spy();
        PortalVisitor::creating(fn () => throw new \RuntimeException('analytics database is down'));

        $this->get(route('portal.faq'))->assertOk();

        $this->assertSame(0, PortalPageView::count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Portal analytics tracking failed')->once();
    }

    public function test_tracking_can_be_disabled(): void
    {
        config(['portal_analytics.enabled' => false]);

        $response = $this->get(route('portal.faq'));

        $response->assertOk();
        $this->assertNull($response->getCookie($this->cookieName));
        $this->assertSame(0, PortalVisitor::count());
    }

    public function test_prune_command_removes_old_page_views_and_idle_visitors_but_keeps_registrations(): void
    {
        $old = now()->subDays(400);
        $fresh = now()->subDay();

        $idle = PortalVisitor::create(['id' => (string) Str::uuid(), 'first_seen_at' => $old, 'last_seen_at' => $old]);
        $registered = PortalVisitor::create(['id' => (string) Str::uuid(), 'first_seen_at' => $old, 'last_seen_at' => $old]);
        $active = PortalVisitor::create(['id' => (string) Str::uuid(), 'first_seen_at' => $old, 'last_seen_at' => $fresh]);

        RegistrationProgress::create(['visitor_id' => $registered->id, 'flow' => 'vendor', 'started_at' => $old, 'last_activity_at' => $old]);
        PortalPageView::create(['visitor_id' => $idle->id, 'path' => '/faq', 'viewed_at' => $old]);
        PortalPageView::create(['visitor_id' => $active->id, 'path' => '/faq', 'viewed_at' => $old]);
        PortalPageView::create(['visitor_id' => $active->id, 'path' => '/faq', 'viewed_at' => $fresh]);

        $this->artisan('portal-analytics:prune', ['--days' => 180])->assertSuccessful();

        $this->assertSame(1, PortalPageView::count());
        $this->assertNull(PortalVisitor::find($idle->id));
        $this->assertNotNull(PortalVisitor::find($registered->id), 'visitors tied to a registration are kept');
        $this->assertNotNull(PortalVisitor::find($active->id));
        $this->assertSame(1, RegistrationProgress::count());
    }
}
