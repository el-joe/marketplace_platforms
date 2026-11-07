<?php

use Carbon\CarbonInterface;

if (! function_exists('admin_tz')) {
    /**
     * Convert a stored (UTC) timestamp to the admin display timezone (config app.admin_timezone).
     * Display only: never use for values that are written back to the database.
     */
    function admin_tz(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->copy()->setTimezone(config('app.admin_timezone', config('app.timezone')));
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
            $timezone = $vendorAdmin?->vendor?->country?->timezone;
            $timezones[$key] = is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
                ? $timezone
                : config('app.admin_timezone', config('app.timezone'));
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
