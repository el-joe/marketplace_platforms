<?php

namespace App\Support;

use App\Models\Country;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the platform's timezone rules.
 *
 *  - Storage: every timestamp is stored and compared in UTC (config app.timezone).
 *  - Admin panel: shown and entered in the admin timezone (config app.admin_timezone, Oman / Asia/Muscat).
 *  - Storefront / seller panel: shown in the country's own timezone (countries.timezone).
 */
final class Timezones
{
    public const DEFAULT_ADMIN = 'Asia/Muscat';

    /** Matches the date-time values posted by admin forms: "2026-11-11 14:30", "2026-11-11T14:30", optional seconds. */
    public const ADMIN_INPUT_PATTERN = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/';

    public static function isValid(?string $timezone): bool
    {
        return is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true);
    }

    /** The admin panel's display/input timezone (Oman). */
    public static function admin(): string
    {
        $timezone = config('app.admin_timezone');

        return self::isValid($timezone) ? $timezone : self::DEFAULT_ADMIN;
    }

    /** A country's local timezone, falling back to the admin timezone when unset or invalid. */
    public static function forCountry(?Country $country): string
    {
        $timezone = $country?->timezone;

        return self::isValid($timezone) ? $timezone : self::admin();
    }

    /**
     * Interpret a wall-clock value typed in the admin panel (Oman time) and return it in UTC,
     * or null when the value is not an admin date-time string.
     */
    public static function adminInputToUtc(string $value): ?CarbonImmutable
    {
        if (! preg_match(self::ADMIN_INPUT_PATTERN, $value)) {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);
        $format = strlen($normalized) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
        $parsed = CarbonImmutable::createFromFormat('!'.$format, $normalized, self::admin());

        return $parsed ? $parsed->utc() : null;
    }

    /**
     * Inverse of the admin input conversion for stored structures (e.g. page-builder block config
     * edited as raw JSON): every UTC "Y-m-d H:i[:s]" string becomes Oman wall time, so posting the
     * structure back through ConvertAdminDateTimeInputs restores the same UTC values.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function utcValuesToAdminInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::utcValuesToAdminInput($value);
            } elseif (is_string($value) && preg_match(self::ADMIN_INPUT_PATTERN, $value)) {
                $data[$key] = Carbon::parse(str_replace('T', ' ', $value), 'UTC')
                    ->setTimezone(self::admin())
                    ->format(strlen($value) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s');
            }
        }

        return $data;
    }

    /** UTC instant at which the given calendar day starts in Oman (inclusive lower bound). */
    public static function adminDayStart(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse(self::dateString($date), self::admin())->startOfDay()->utc();
    }

    /** UTC instant at which the given calendar day ends in Oman (inclusive upper bound). */
    public static function adminDayEnd(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse(self::dateString($date), self::admin())->endOfDay()->utc();
    }

    private static function dateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->format('Y-m-d') : substr(trim($date), 0, 10);
    }
}
