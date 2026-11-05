<?php

namespace Tests\Feature\Portal;

use App\Enums\RegistrationFlow;
use App\Models\Country;
use App\Models\PortalVisitor;
use App\Models\RegistrationProgress;
use App\Models\TravelAgency;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Registration funnel: step events for the vendor and travel-agency wizards, abandonment,
 * duplicate prevention, the browser-only step 4 beacon and the link to the created account.
 */
class RegistrationFunnelTrackingTest extends TestCase
{
    use RefreshDatabase;

    private string $visitorId;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'omn', 'iso_code_2' => 'OM', 'is_active' => true, 'is_launched' => true]);

        // One anonymous visitor (cookie) for the whole test, as a real browser would have.
        $this->visitorId = (string) Str::uuid();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0 Safari/537.36')
            ->withCookie(config('portal_analytics.cookie.name'), $this->visitorId)
            ->withCredentials(); // JSON requests only carry the visitor cookie with credentials, like a browser
    }

    /** @return array<string, string> */
    private function vendorStep(int $step): array
    {
        return match ($step) {
            1 => ['name' => 'Amina Seller', 'email' => 'amina@example.com', 'phone' => '+96890000000', 'gender' => 'female', 'country_id' => $this->scenario->country->id, 'password' => 'password123', 'password_confirmation' => 'password123'],
            2 => ['store_name' => 'Amina Store', 'store_slug' => 'amina-store', 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            3 => ['contact_email' => 'amina@example.com', 'contact_phone' => '+96890000000', 'city_id' => $this->scenario->city->id, 'street_address' => 'Al Khuwair'],
        };
    }

    private function postVendorStep(int $step, ?array $payload = null)
    {
        return $this->postJson(route('portal.register.step', $step), $payload ?? $this->vendorStep($step));
    }

    private function progress(RegistrationFlow $flow = RegistrationFlow::Vendor): ?RegistrationProgress
    {
        return RegistrationProgress::where('visitor_id', $this->visitorId)->where('flow', $flow->value)->first();
    }

    /** @return array<string, int> "step:event" => occurrences */
    private function events(?RegistrationProgress $progress = null): array
    {
        $progress ??= $this->progress();

        $events = $progress->events()->get()
            ->mapWithKeys(fn ($e) => ["{$e->step}:{$e->event->value}" => $e->occurrences])->all();
        ksort($events);

        return $events;
    }

    public function test_opening_the_wizard_records_the_start_and_step_one_view(): void
    {
        $this->get(route('portal.register'))->assertOk();

        $progress = $this->progress();
        $this->assertNotNull($progress);
        $this->assertSame(['1:viewed' => 1], $this->events($progress));
        $this->assertSame(1, $progress->highest_step_reached);
        $this->assertNull($progress->first_submitted_at);
        $this->assertNull($progress->completed_at);
        $this->assertSame('omn', $progress->country_code);
        $this->assertSame(1, PortalVisitor::count());
    }

    public function test_vendor_steps_progress_through_the_funnel(): void
    {
        $this->get(route('portal.register'));
        $this->postVendorStep(1)->assertOk();
        $this->postVendorStep(2)->assertOk();

        $progress = $this->progress();
        $this->assertSame(
            ['1:completed' => 1, '1:viewed' => 1, '2:completed' => 1, '2:viewed' => 1, '3:viewed' => 1],
            $this->events($progress),
        );
        $this->assertSame(3, $progress->highest_step_reached);
        $this->assertSame(2, $progress->last_step_completed);
        $this->assertNotNull($progress->first_submitted_at);
    }

    public function test_a_visitor_who_never_completes_a_step_is_an_abandonment_at_that_step(): void
    {
        $this->postVendorStep(1)->assertOk();
        $this->postVendorStep(2)->assertOk();
        // Reached step 3 (contact), then left.

        $events = $this->events();
        $this->assertArrayHasKey('3:viewed', $events);
        $this->assertArrayNotHasKey('3:completed', $events);
        $this->assertNull($this->progress()->completed_at);
    }

    public function test_repeating_a_step_does_not_duplicate_events(): void
    {
        $this->get(route('portal.register'));
        $this->get(route('portal.register'));
        $this->postVendorStep(1)->assertOk();
        $this->postVendorStep(1)->assertOk();
        $this->postVendorStep(1)->assertOk();

        $this->assertSame(1, RegistrationProgress::count());
        $this->assertSame(['1:completed' => 1, '1:viewed' => 1, '2:viewed' => 1], $this->events());
    }

    public function test_rejected_submissions_are_counted_as_validation_failures_without_storing_the_input(): void
    {
        $this->postVendorStep(1, ['email' => 'not-an-email'])->assertStatus(422);
        $this->postVendorStep(1, ['email' => 'still-wrong'])->assertStatus(422);

        $progress = $this->progress();
        $events = $this->events($progress);
        $this->assertSame(2, $events['1:validation_failed']);
        $this->assertSame(1, $progress->events()->where('event', 'validation_failed')->count());
        $this->assertArrayNotHasKey('1:completed', $events);
        $this->assertNotNull($progress->first_submitted_at, 'a rejected submission still counts as an attempt');

        $dump = json_encode($progress->events()->get()->toArray()).json_encode($progress->toArray());
        $this->assertStringNotContainsString('not-an-email', $dump);
    }

    public function test_beacon_records_the_browser_only_documents_step(): void
    {
        $this->postVendorStep(1);
        $this->postVendorStep(2);
        $this->postVendorStep(3);

        $this->postJson(route('portal.register.track'), ['flow' => 'vendor', 'step' => 4, 'event' => 'completed'])
            ->assertOk()->assertJson(['success' => true]);
        // Sent twice (e.g. back and forward in the wizard): still one event.
        $this->postJson(route('portal.register.track'), ['flow' => 'vendor', 'step' => 4, 'event' => 'completed'])->assertOk();

        $events = $this->events();
        $this->assertSame(1, $events['4:completed']);
        $this->assertSame(1, $events['5:viewed']);
        $this->assertSame(5, $this->progress()->highest_step_reached);
        $this->assertSame(4, $this->progress()->last_step_completed);
    }

    public function test_beacon_rejects_invalid_flows_steps_events_and_forged_progress(): void
    {
        $url = route('portal.register.track');

        // Nothing saved in the session yet: cannot claim step 4.
        $this->postJson($url, ['flow' => 'vendor', 'step' => 4, 'event' => 'completed'])->assertStatus(422);

        $this->postVendorStep(1);
        $this->postVendorStep(2);
        $this->postVendorStep(3);

        $this->postJson($url, ['flow' => 'nope', 'step' => 4, 'event' => 'completed'])->assertStatus(422);
        $this->postJson($url, ['flow' => 'vendor', 'step' => 2, 'event' => 'completed'])->assertStatus(422);   // saved server-side, not client-reported
        $this->postJson($url, ['flow' => 'vendor', 'step' => 99, 'event' => 'completed'])->assertStatus(422);
        $this->postJson($url, ['flow' => 'vendor', 'step' => 'x', 'event' => 'completed'])->assertStatus(422);
        $this->postJson($url, ['flow' => 'vendor', 'step' => 4, 'event' => 'validation_failed'])->assertStatus(422);
        $this->postJson($url, ['flow' => 'travel_agency', 'step' => 4, 'event' => 'completed'])->assertStatus(422);
        $this->postJson($url, [])->assertStatus(422);

        $this->assertArrayNotHasKey('4:completed', $this->events());
    }

    public function test_beacon_cannot_name_a_visitor_or_country(): void
    {
        $this->postVendorStep(1);
        $this->postVendorStep(2);
        $this->postVendorStep(3);

        $other = (string) Str::uuid();
        $this->postJson(route('portal.register.track'), ['flow' => 'vendor', 'step' => 4, 'event' => 'completed', 'visitor_id' => $other, 'country_code' => 'xx'])
            ->assertOk();

        $this->assertSame(0, RegistrationProgress::where('visitor_id', $other)->count());
        $this->assertNotSame('xx', $this->progress()->country_code);
    }

    public function test_completing_the_registration_links_the_created_vendor(): void
    {
        $this->get(route('portal.register'));
        $this->postVendorStep(1)->assertOk();
        $this->postVendorStep(2)->assertOk();
        $this->postVendorStep(3)->assertOk();
        $this->postJson(route('portal.register.track'), ['flow' => 'vendor', 'step' => 4, 'event' => 'completed'])->assertOk();

        $this->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertOk()->assertJsonPath('success', true);

        $vendor = Vendor::where('email', 'amina@example.com')->firstOrFail();
        $progress = $this->progress();

        $this->assertNotNull($progress->completed_at);
        $this->assertSame($vendor->id, $progress->vendor_id);
        $this->assertSame(5, $progress->last_step_completed);

        $events = $this->events($progress);
        foreach (range(1, 5) as $step) {
            $this->assertSame(1, $events["{$step}:viewed"] ?? 0, "step {$step} viewed");
            $this->assertSame(1, $events["{$step}:completed"] ?? 0, "step {$step} completed");
        }
    }

    public function test_a_rejected_final_submission_is_a_validation_failure_on_the_review_step(): void
    {
        $this->postVendorStep(1);
        $this->postVendorStep(2);
        $this->postVendorStep(3);

        $this->postJson(route('portal.register.complete'), [])->assertStatus(422);

        $this->assertSame(1, $this->events()['5:validation_failed']);
        $this->assertNull($this->progress()->completed_at);
    }

    public function test_travel_agency_wizard_is_tracked_as_its_own_flow_and_linked_on_completion(): void
    {
        $country = Country::factory()->create(['is_active' => true]);

        $this->get(route('portal.companies.travel-agency.register.show'))->assertOk();
        $this->postJson(route('portal.companies.travel-agency.register.step', 1), [
            'name' => 'Owner', 'email' => 'agency-owner@example.com', 'phone' => '+971500000000',
            'country_id' => $country->id, 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertOk();

        $progress = $this->progress(RegistrationFlow::TravelAgency);
        $this->assertSame(['1:completed' => 1, '1:viewed' => 1, '2:viewed' => 1], $this->events($progress));
        $this->assertNull($this->progress(RegistrationFlow::Vendor), 'the vendor funnel is untouched');

        $this->postJson(route('portal.companies.travel-agency.register.step', 2), ['agency_name' => 'Horizon Travel'])->assertOk();
        $this->postJson(route('portal.companies.travel-agency.register.complete'), ['terms_agreed' => true, 'privacy_agreed' => true])->assertOk();

        $progress = $this->progress(RegistrationFlow::TravelAgency)->fresh();
        $this->assertNotNull($progress->completed_at);
        $this->assertSame(TravelAgency::where('email', 'agency-owner@example.com')->value('id'), $progress->travel_agency_id);
        $this->assertNull($progress->vendor_id);
        $this->assertSame(3, $progress->last_step_completed);
    }

    public function test_bots_leave_no_funnel_trace(): void
    {
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('portal.register'))->assertOk();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->postJson(route('portal.register.step', 1), $this->vendorStep(1))->assertOk();

        $this->assertSame(0, RegistrationProgress::count());
    }
}
