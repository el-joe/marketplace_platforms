<?php

namespace App\Models;

use App\Services\FooterService;
use App\Support\Bilingual;
use App\Support\SafeCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed bilingual content pages (About Us, Terms & Conditions, Privacy
 * Policy, Return / Shipping Policy, Contact, ...).
 *
 * `audience` decides which site renders the page: the seller portal (Blade,
 * routes/portal.php), the customer storefront (Next.js via
 * routes/api_customer_v1.php), or both. `show_in_footer` + `footer_group` +
 * `sort_order` drive the footer links on each site.
 *
 * Not to be confused with CustomPage (a product-collection landing page).
 */
class StaticPage extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const AUDIENCE_PORTAL = 'portal';

    public const AUDIENCE_STOREFRONT = 'storefront';

    public const AUDIENCE_BOTH = 'both';

    public const AUDIENCES = [self::AUDIENCE_PORTAL, self::AUDIENCE_STOREFRONT, self::AUDIENCE_BOTH];

    public const FOOTER_GROUP_COMPANY = 'company';

    public const FOOTER_GROUP_POLICIES = 'policies';

    public const FOOTER_GROUP_HELP = 'help';

    public const FOOTER_GROUPS = [self::FOOTER_GROUP_COMPANY, self::FOOTER_GROUP_POLICIES, self::FOOTER_GROUP_HELP];

    /** Bumped on every write; part of every static-page cache key (database cache store has no tags). */
    public const CACHE_VERSION_KEY = 'static_pages_cache_version';

    protected $fillable = [
        'slug',
        'title_en',
        'title_ar',
        'content_en',
        'content_ar',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
        'audience',
        'show_in_footer',
        'footer_group',
        'sort_order',
        'is_active',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => static::flushCache();

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
        static::forceDeleted($flush);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Pages shown on the given site ('portal' or 'storefront'), including 'both'. */
    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->whereIn('audience', [$audience, self::AUDIENCE_BOTH]);
    }

    public function scopeForPortal(Builder $query): Builder
    {
        return $query->forAudience(self::AUDIENCE_PORTAL);
    }

    public function scopeForStorefront(Builder $query): Builder
    {
        return $query->forAudience(self::AUDIENCE_STOREFRONT);
    }

    public function scopeInFooter(Builder $query): Builder
    {
        return $query->where('show_in_footer', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title_en');
    }

    // ─── Localized helpers (portal/Blade: session locale; API: use Bilingual::pair) ───

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localized('title', $locale);
    }

    public function localizedContent(?string $locale = null): ?string
    {
        return $this->localized('content', $locale);
    }

    /** SEO title, falling back to the page title. */
    public function localizedSeoTitle(?string $locale = null): string
    {
        return (string) ($this->localized('seo_title', $locale) ?: $this->localizedTitle($locale));
    }

    public function localizedSeoDescription(?string $locale = null): ?string
    {
        return $this->localized('seo_description', $locale);
    }

    /** Requested language, falling back to the other one when empty. */
    protected function localized(string $field, ?string $locale = null): ?string
    {
        $locale = ($locale ?? session('locale', 'ar')) === 'ar' ? 'ar' : 'en';
        $other = $locale === 'ar' ? 'en' : 'ar';

        return $this->{"{$field}_{$locale}"} ?: ($this->{"{$field}_{$other}"} ?: null);
    }

    public function showsOn(string $audience): bool
    {
        return $this->audience === self::AUDIENCE_BOTH || $this->audience === $audience;
    }

    // ─── Cache ────────────────────────────────────────────────────────────

    public static function cacheKey(string $suffix): string
    {
        return 'static_pages_v'.Cache::get(self::CACHE_VERSION_KEY, 1).'.'.$suffix;
    }

    /**
     * Invalidate every static-page cache entry (version bump) and the
     * storefront footer payload (FooterService), which may embed page links.
     */
    public static function flushCache(): void
    {
        Cache::put(self::CACHE_VERSION_KEY, (int) Cache::get(self::CACHE_VERSION_KEY, 1) + 1);
        FooterService::flushCache();
    }

    /**
     * Footer links for one site, cached as plain arrays (the database cache
     * store corrupts serialized models — see PortalContent::forPage()).
     *
     * @return array<int, array{id: string, slug: string, title: array{ar: ?string, en: ?string}, footer_group: ?string, sort_order: int}>
     */
    public static function footerLinksFor(string $audience): array
    {
        return SafeCache::remember(static::cacheKey("footer.{$audience}"), 3600, fn () => static::query()
            ->active()
            ->inFooter()
            ->forAudience($audience)
            ->ordered()
            ->get(['id', 'slug', 'title_en', 'title_ar', 'footer_group', 'sort_order'])
            ->map(fn (self $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => Bilingual::pair($page, 'title'),
                'footer_group' => $page->footer_group,
                'sort_order' => $page->sort_order,
            ])
            ->values()
            ->all());
    }
}
