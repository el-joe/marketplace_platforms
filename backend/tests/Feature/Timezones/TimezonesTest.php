<?php

namespace Tests\Feature\Timezones;

use App\Models\Country;
use App\Support\Timezones;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Platform timezone rules: storage UTC, admin panel Oman (Asia/Muscat, UTC+4), storefront per country.
 */
class TimezonesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_timezone' => 'Asia/Muscat']);
    }

    public function test_admin_timezone_is_oman_and_falls_back_when_misconfigured(): void
    {
        $this->assertSame('Asia/Muscat', Timezones::admin());

        config(['app.admin_timezone' => 'Not/AZone']);
        $this->assertSame(Timezones::DEFAULT_ADMIN, Timezones::admin());
    }

    public function test_country_timezone_with_fallback_to_admin(): void
    {
        $this->assertSame('Africa/Cairo', Timezones::forCountry(new Country(['timezone' => 'Africa/Cairo'])));
        $this->assertSame('Asia/Muscat', Timezones::forCountry(new Country(['timezone' => 'bogus'])));
        $this->assertSame('Asia/Muscat', Timezones::forCountry(null));
    }

    public function test_admin_input_is_read_as_oman_time_and_returned_in_utc(): void
    {
        $this->assertSame('2026-11-10 20:00:00', Timezones::adminInputToUtc('2026-11-11 00:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 20:00:00', Timezones::adminInputToUtc('2026-11-11T00:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 10:30:15', Timezones::adminInputToUtc('2026-11-11 14:30:15')->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', Timezones::adminInputToUtc('2026-11-11 14:30')->getTimezone()->getName());
    }

    public function test_non_date_time_values_are_not_admin_input(): void
    {
        foreach (['2026-11-11', '14:30', '2026-11-11T14:30:00Z', '2026-11-11T14:30:00+04:00', 'abc', ''] as $value) {
            $this->assertNull(Timezones::adminInputToUtc($value), $value);
        }
    }

    public function test_admin_day_bounds_are_oman_midnight_in_utc(): void
    {
        $this->assertSame('2026-11-10 20:00:00', admin_day_start('2026-11-11')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 19:59:59', admin_day_end('2026-11-11')->format('Y-m-d H:i:s'));
    }

    public function test_admin_today_range_follows_oman_date_not_utc_date(): void
    {
        // 22:00 UTC on 11 Nov is already 02:00 on 12 Nov in Oman.
        Carbon::setTestNow(Carbon::parse('2026-11-11 22:00:00', 'UTC'));

        [$start, $end] = admin_day_range();

        $this->assertSame('2026-11-11 20:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-12 19:59:59', $end->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_stored_config_round_trips_through_admin_input_unchanged(): void
    {
        $stored = ['title' => 'Sale', 'ends_at' => '2026-11-11 20:30:00', 'slides' => [['visible_from' => '2026-11-11 20:30']], 'day' => '2026-11-11'];

        $shown = Timezones::utcValuesToAdminInput($stored);
        $this->assertSame('2026-11-12 00:30:00', $shown['ends_at']);
        $this->assertSame('2026-11-12 00:30', $shown['slides'][0]['visible_from']);
        $this->assertSame('2026-11-11', $shown['day']);

        $this->assertSame('2026-11-11 20:30:00', Timezones::adminInputToUtc($shown['ends_at'])->format('Y-m-d H:i:s'));
    }

    public function test_display_helpers(): void
    {
        $utc = Carbon::parse('2026-11-11 20:30:00', 'UTC');

        $this->assertSame('2026-11-12 00:30', admin_input_value($utc));
        $this->assertSame('2026-11-12T00:30', admin_input_value($utc, 'Y-m-d\TH:i'));
        $this->assertSame('', admin_input_value(null));
        $this->assertSame('2026-11-12T00:30', admin_input_value('2026-11-11 20:30:00', 'Y-m-d\TH:i'), 'uncast attribute (raw UTC string)');
        $this->assertSame('2026-11-11 22:30 GMT+02:00', country_tz($utc, new Country(['timezone' => 'Africa/Cairo']))->format('Y-m-d H:i').' '.tz_label(country_tz($utc, new Country(['timezone' => 'Africa/Cairo']))));
        $this->assertSame('UTC', $utc->getTimezone()->getName(), 'helpers must not mutate the stored value');
    }
}
