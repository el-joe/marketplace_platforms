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
