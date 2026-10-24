<?php

namespace App\Models;

use App\Enums\PortalContentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Generic, admin-editable bilingual content block used to power static
 * marketing text and links on the public portal (resources/views/portal/*).
 *
 * Rows are grouped by page_key/block_key/field_key, e.g.:
 *   page_key='faq', block_key='faq_item_1', field_key='question', type='text'
 *   page_key='home', block_key='hero', field_key='cta_button', type='link'
 *
 * See app/Helpers/portal_content.php for the blade-facing helpers
 * (portal_content() / portal_link()) that read from this model.
 */
class PortalContent extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_code',
        'page_key',
        'block_key',
        'field_key',
        'type',
        'value_en',
        'value_ar',
        'value_url',
        'sort_order',
        'is_active',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'type' => PortalContentType::class,
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    // ─── Static helpers ───────────────────────────────────────────────────────

    /**
     * Resolved rows for a page + country, cached and keyed by "{block_key}.{field_key}".
     *
     * Merge rule per field: the country's row wins when it exists, is active and
     * non-empty; otherwise the global ('*') row; otherwise the key is absent (the
     * Blade helpers then use their hardcoded fallback). A country row that is
     * inactive/empty with no global row is still returned so callers see it.
     *
     * NOTE: the cache payload is stored as plain scalar arrays (not
     * serialized Eloquent objects) and rehydrated into models on read.
     * The 'database' cache driver in this environment corrupts serialized
     * PHP object graphs on roundtrip (confirmed to affect Setting::getGroup()
     * identically), so caching arrays here avoids that failure mode.
     */
    public static function forPage(string $pageKey, ?string $country = null): Collection
    {
        $country = static::resolveCountryCode($country);

        $rows = Cache::remember(static::cacheKey($country, $pageKey), 3600, function () use ($pageKey, $country) {
            $codes = $country === '*' ? ['*'] : [$country, '*'];

            $merged = [];
            foreach (static::whereIn('country_code', $codes)->where('page_key', $pageKey)->orderBy('block_key')->orderBy('sort_order')->get() as $row) {
                $key = $row->block_key.'.'.$row->field_key;
                $existing = $merged[$key] ?? null;

                if ($existing === null) {
                    $merged[$key] = $row;
                } elseif ($row->country_code === $country && $country !== '*') {
                    // Country row vs. the global row already stored: prefer the country row if usable.
                    if ($row->isUsable()) {
                        $merged[$key] = $row;
                    }
                } elseif ($existing->country_code === $country && ! $existing->isUsable()) {
                    // Global row arrived after an unusable country row: global wins.
                    $merged[$key] = $row;
                }
            }

            return collect($merged)->map(fn (PortalContent $row) => $row->attributesToArray())->values()->all();
        });

        return collect($rows)
            ->map(function (array $attributes) {
                $model = new static;
                $model->setRawAttributes($attributes, true);
                $model->exists = true;

                return $model;
            })
            ->keyBy(fn (PortalContent $row) => $row->block_key.'.'.$row->field_key);
    }

    /** Active and carrying at least one non-empty value. */
    public function isUsable(): bool
    {
        return $this->is_active
            && ($this->value_en !== null && $this->value_en !== ''
                || $this->value_ar !== null && $this->value_ar !== ''
                || $this->value_url !== null && $this->value_url !== '');
    }

    /**
     * Country code used to read content: explicit param → {country} route
     * segment → session('portal_country') → Country::resolveSiteCode().
     * Falls back to '*' (global layer) when nothing resolves.
     */
    public static function resolveCountryCode(?string $country = null): string
    {
        $route = request()->route();
        $routeCountry = $route ? $route->parameter('country') : null;
        $routeCountry = is_string($routeCountry) ? $routeCountry : null;

        $code = $country ?: $routeCountry ?: session('portal_country');

        if (! $code) {
            // IP / Accept-Language / first-launched lookup: hit once per request, not once per helper call.
            $attrs = request()->attributes;
            if (! $attrs->has('portal_content_country')) {
                $attrs->set('portal_content_country', Country::resolveSiteCode(null));
            }
            $code = $attrs->get('portal_content_country');
        }

        return is_string($code) && $code !== '' ? strtolower($code) : '*';
    }

    /**
     * Clear cached rows for a page. With a country, only that country; with
     * null or '*' (global layer changed) every country and the global layer.
     */
    public static function flush(string $pageKey, ?string $country = null): void
    {
        if ($country !== null && $country !== '*') {
            Cache::forget(static::cacheKey(strtolower($country), $pageKey));

            return;
        }

        $codes = ['*'];
        if (Schema::hasTable('countries')) {
            $codes = array_merge($codes, Country::withTrashed()->whereNotNull('site_code')->pluck('site_code')->all());
        }
        if (Schema::hasColumn('portal_contents', 'country_code')) {
            $codes = array_merge($codes, static::query()->distinct()->pluck('country_code')->all());
        }

        foreach (array_unique(array_map('strtolower', $codes)) as $code) {
            Cache::forget(static::cacheKey($code, $pageKey));
        }
        Cache::forget('portal_content:'.$pageKey); // legacy key
    }

    private static function cacheKey(string $country, string $pageKey): string
    {
        return 'portal_content:'.$country.':'.$pageKey;
    }

    /**
     * Turn a stored value_url into a browser-usable URL.
     *
     * Absolute URLs (http/https, protocol-relative), mailto:/tel:/# and
     * site-absolute paths ("/images/logo.png") are returned untouched;
     * anything else is treated as a path on the "public" disk (admin
     * uploads are stored that way, e.g. "portal-content/home/x.jpg").
     */
    public static function resolveUrl(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//|^(mailto:|tel:|\#|/)#i', $value)) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
