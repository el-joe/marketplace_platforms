<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Global version stamp for every storefront cache that embeds listing data
 * (page-builder blocks, app config, browse blocks, seller profile). Bumping it
 * orphans all of those keys at once, so a listing add/edit/delete shows up
 * immediately regardless of the per-key TTL. Same pattern as the other
 * *_CACHE_VERSION_KEY constants in the codebase.
 */
class ListingCacheVersion
{
    public const KEY = 'listings_cache_version';

    public static function current(): int
    {
        return (int) Cache::get(self::KEY, 1);
    }

    public static function bump(): void
    {
        Cache::put(self::KEY, self::current() + 1);
    }
}
