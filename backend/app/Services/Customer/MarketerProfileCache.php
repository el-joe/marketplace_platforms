<?php

namespace App\Services\Customer;

use Illuminate\Support\Facades\Cache;

/**
 * Version-tagged cache key for the public marketer profile response
 * (Api\Public\MarketerProfileController::show). Driver-agnostic — works
 * identically on file, database, redis, array — because it never relies
 * on cache tags (file/database stores don't support them). Instead each
 * slug has a "version" counter; bumping it changes the key so old
 * responses are never read again and simply expire on their own TTL.
 */
class MarketerProfileCache
{
    public static function key(string $slug, string $countryId): string
    {
        return "marketer_profile:{$slug}:v" . self::version($slug) . ":{$countryId}";
    }

    public static function version(string $slug): int
    {
        return (int) Cache::get(self::versionKey($slug), 1);
    }

    /**
     * Invalidate every cached response for this slug by advancing its version.
     * Read-then-write instead of Cache::increment() because the database
     * cache driver's increment() returns false (does not create the row)
     * when the key doesn't exist yet — read-then-write behaves the same
     * across every driver.
     */
    public static function bump(?string $slug): void
    {
        if (!$slug) {
            return;
        }

        $key = self::versionKey($slug);
        Cache::forever($key, self::version($slug) + 1);
    }

    private static function versionKey(string $slug): string
    {
        return "marketer_profile_version:{$slug}";
    }
}
