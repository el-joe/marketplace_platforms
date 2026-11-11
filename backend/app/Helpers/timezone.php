<?php

use App\Models\Country;
use App\Support\Timezones;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('admin_tz')) {
    /**
     * Convert a stored (UTC) timestamp to the admin display timezone (config app.admin_timezone).
     * Display only: never use for values that are written back to the database.
     */
    function admin_tz(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->copy()->setTimezone(Timezones::admin());
    }
}

if (! function_exists('vendor_tz')) {
    /**
     * Convert a stored (UTC) timestamp to the signed-in seller's local time (the timezone of the seller's country),
     * falling back to the admin display timezone. Display only.
     */
    function vendor_tz(?CarbonInterface $date): ?CarbonInterface
    {
        if (! $date) {
            return null;
        }

        static $timezones = [];

        $vendorAdmin = auth('vendor')->user();
        $key = $vendorAdmin?->getAuthIdentifier() ?? '';

        if (! array_key_exists($key, $timezones)) {
            $timezones[$key] = Timezones::forCountry($vendorAdmin?->vendor?->country);
        }

        return $date->copy()->setTimezone($timezones[$key]);
    }
}

if (! function_exists('tz_label')) {
    /**
     * The UTC offset of an already-converted timestamp, e.g. "GMT+04:00", so a displayed time is never ambiguous.
     */
    function tz_label(?CarbonInterface $date): string
    {
        return $date ? 'GMT'.$date->format('P') : '';
    }
}

if (! function_exists('format_tz')) {
    /**
     * Format an already-converted timestamp followed by its UTC offset, e.g. "07/11/2026 03:11 (GMT+04:00)".
     * Wrapped in a left-to-right isolate so the order holds inside Arabic (RTL) text.
     */
    function format_tz(?CarbonInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return $date ? "\u{2066}".$date->format($format).' ('.tz_label($date).')'."\u{2069}" : '';
    }
}

if (! function_exists('country_tz')) {
    /**
     * Convert a stored (UTC) timestamp to a country's local time (countries.timezone),
     * falling back to the admin timezone. Display only.
     */
    function country_tz(?CarbonInterface $date, ?Country $country): ?CarbonInterface
    {
        return $date?->copy()->setTimezone(Timezones::forCountry($country));
    }
}

if (! function_exists('admin_input_value')) {
    /**
     * Pre-fill value for an admin date-time input, in the admin timezone (Oman), so it round-trips
     * through the ConvertAdminDateTimeInputs middleware unchanged. Accepts a Carbon value or a raw stored
     * UTC string (uncast attribute). Empty string when there is no date.
     */
    function admin_input_value(CarbonInterface|string|null $date, string $format = 'Y-m-d H:i'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        // Attributes without a datetime cast arrive as the raw stored (UTC) string.
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date, 'UTC');

        return admin_tz($date)->format($format);
    }
}

if (! function_exists('admin_day_start')) {
    /**
     * UTC lower bound of an admin-selected calendar day ("Y-m-d", Oman time), for date filters
     * on UTC columns: ->where('created_at', '>=', admin_day_start($from)).
     */
    function admin_day_start(CarbonInterface|string $date): Carbon
    {
        return Timezones::adminDayStart($date);
    }
}

if (! function_exists('admin_day_end')) {
    /**
     * UTC upper bound (inclusive) of an admin-selected calendar day ("Y-m-d", Oman time):
     * ->where('created_at', '<=', admin_day_end($to)).
     */
    function admin_day_end(CarbonInterface|string $date): Carbon
    {
        return Timezones::adminDayEnd($date);
    }
}

if (! function_exists('admin_day_range')) {
    /**
     * [start, end] UTC bounds of a calendar day in Oman (default: today in Oman), for
     * ->whereBetween('created_at', admin_day_range()) instead of ->whereDate('created_at', today()).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    function admin_day_range(CarbonInterface|string|null $date = null): array
    {
        $date ??= admin_tz(now());

        return [Timezones::adminDayStart($date), Timezones::adminDayEnd($date)];
    }
}
