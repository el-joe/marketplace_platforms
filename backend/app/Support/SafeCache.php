<?php

namespace App\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache helper that protects against corrupted/malformed cached values.
 *
 * When Cache::remember encounters a corrupted entry (bad serialization,
 * truncated DB row, schema-changed array), it returns the corrupt value
 * silently or throws. SafeCache catches that, purges the bad key, and
 * re-runs the closure to rebuild a clean cache entry.
 *
 * Usage:
 *   SafeCache::remember('my-key', 600, fn() => [...]);
 *   SafeCache::tags(['pages'])->remember('my-key', 300, fn() => [...]);
 *
 * The 'database' cache store configured in this project (CACHE_STORE=database)
 * doesn't support tagging, so tags() falls back to a plain (untagged) remember()
 * when the active store isn't taggable — tags kick in for free if the store is
 * later switched to redis/memcached.
 */
class SafeCache
{
    private function __construct(private readonly array $tags = []) {}

    public static function tags(array $tags): self
    {
        return new self($tags);
    }

    public static function remember(string $key, int $ttl, Closure $callback): mixed
    {
        return (new self())->doRemember($key, $ttl, $callback);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'remember') {
            return $this->doRemember(...$arguments);
        }

        throw new \BadMethodCallException("Method [{$name}] does not exist on " . static::class);
    }

    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        if ($this->tags && Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->tags);
        }

        return Cache::store();
    }

    private function doRemember(string $key, int $ttl, Closure $callback): mixed
    {
        try {
            $value = $this->store()->get($key);

            if ($value !== null) {
                if (!is_array($value)) {
                    throw new \UnexpectedValueException(
                        "Cache key [{$key}] returned a non-array value: " . gettype($value)
                    );
                }

                return $value;
            }
        } catch (\Throwable $e) {
            Log::warning("SafeCache: corrupted cache key [{$key}], purging and rebuilding.", [
                'error' => $e->getMessage(),
            ]);

            try {
                $this->store()->forget($key);
            } catch (\Throwable) {
                // Ignore forget errors — store may be completely unavailable
            }
        }

        $fresh = $callback();

        try {
            $this->store()->put($key, $fresh, $ttl);
        } catch (\Throwable $e) {
            Log::warning("SafeCache: failed to write cache key [{$key}].", [
                'error' => $e->getMessage(),
            ]);
        }

        return $fresh;
    }
}
