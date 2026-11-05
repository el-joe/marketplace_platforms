<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Http\Middleware\SetPortalCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'iso_code_2',
        'iso_code_3',
        'name_ar',
        'name_en',
        'flag_emoji',
        'phone_prefix',
        'currency_code',
        'site_code',
        'site_domain',
        'default_locale',
        'timezone',
        'vat_rate',
        'is_active',
        'is_launched',
        'cod_available',
        'cod_max_amount',
        'cod_supermall_max_amount',
        'cod_supermall_category_id',
        'launched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_launched' => 'boolean',
        'cod_available' => 'boolean',
        'cod_max_amount' => DecimalMoney::class,
        'cod_supermall_max_amount' => DecimalMoney::class,
        'cod_supermall_category_id' => 'string',
        'vat_rate' => 'decimal:2',
        'launched_at' => 'datetime',
    ];

    /** Session key holding the visitor's effective portal country (a `site_code`). */
    public const PORTAL_SESSION_KEY = 'portal_country';

    /** Request attribute set by {@see SetPortalCountry} for the current request. */
    public const PORTAL_REQUEST_ATTRIBUTE = 'portal_country';

    /** Countries always offered in the portal switcher even before `is_launched` (ISO 3166-1 alpha-2). */
    public const PORTAL_ALWAYS_SELECTABLE_ISO = ['OM'];

    /** Seconds a successful IP geo lookup is cached. */
    private const GEO_CACHE_TTL = 86400;

    /** Seconds a failed/empty IP geo lookup is cached, so a transient outage heals quickly. */
    private const GEO_FAILURE_CACHE_TTL = 300;

    /**
     * Countries a portal visitor may be on: active and launched, plus the
     * always-selectable ones (Oman). This is the single "selectable" notion
     * shared by the switcher, `/country/{country}`, `{country}` URL segments,
     * detection and the join selection validation.
     *
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopePortalSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('site_code')
            ->where(function (Builder $q): void {
                $q->where('is_launched', true)
                    ->orWhereIn('iso_code_2', self::PORTAL_ALWAYS_SELECTABLE_ISO);
            });
    }

    /**
     * Whether the given site_code is a country a portal visitor may select.
     */
    public static function isPortalSelectable(?string $siteCode): bool
    {
        return is_string($siteCode)
            && $siteCode !== ''
            && static::portalSelectable()->where('site_code', $siteCode)->exists();
    }

    /**
     * The single default portal site_code, used wherever no country was chosen
     * or detected (header, content, generated links, redirects) so display and
     * content can never disagree.
     *
     * Order: admin setting `portal_default_country` (if selectable), Oman (the
     * imagery default of the join/portal pages), then the first selectable
     * country by English name. Null only when no country is selectable.
     */
    public static function defaultSiteCode(): ?string
    {
        $configured = Setting::get('portal_default_country');
        if (is_string($configured) && static::isPortalSelectable($configured)) {
            return $configured;
        }

        return static::portalSelectable()
            ->whereIn('iso_code_2', self::PORTAL_ALWAYS_SELECTABLE_ISO)
            ->value('site_code')
            ?? static::portalSelectable()->orderBy('name_en')->orderBy('site_code')->value('site_code');
    }

    /**
     * Resolve the effective site_code for the portal.
     *
     * Resolution order:
     *  1. Explicit argument (the URL {country} segment, already validated by the middleware)
     *  2. Value computed for this request by SetPortalCountry
     *  3. Session `portal_country` (manual picker / country switcher / earlier detection)
     *  4. IP / Accept-Language detection (remembered in the session)
     *  5. {@see self::defaultSiteCode()} (remembered in the session)
     */
    public static function resolveSiteCode(?string $country): ?string
    {
        if ($country) {
            return $country;
        }

        $request = request();

        if (($resolved = $request->attributes->get(self::PORTAL_REQUEST_ATTRIBUTE)) && is_string($resolved)) {
            return $resolved;
        }

        if (($picked = session(self::PORTAL_SESSION_KEY)) && is_string($picked)) {
            return $picked;
        }

        $resolved = static::detectAndRemember() ?? static::defaultSiteCode();

        if ($resolved) {
            static::rememberForRequest($resolved);
        }

        return $resolved;
    }

    /**
     * Store the effective country in the session and on the current request.
     */
    public static function rememberForRequest(string $siteCode): void
    {
        $request = request();

        if ($request->hasSession() && $request->session()->get(self::PORTAL_SESSION_KEY) !== $siteCode) {
            $request->session()->put(self::PORTAL_SESSION_KEY, $siteCode);
        }

        $request->attributes->set(self::PORTAL_REQUEST_ATTRIBUTE, $siteCode);
    }

    /**
     * Auto-detect the visitor's country on their first visit and store it in the
     * session (`portal_country`) so every portal page and the switcher agree and
     * no further lookups are made. A manual pick simply overwrites it.
     */
    public static function detectAndRemember(): ?string
    {
        $detected = static::resolveFromRequest(request());

        if ($detected) {
            static::rememberForRequest($detected);
        }

        return $detected;
    }

    /**
     * Resolve site_code from the full HTTP request: IP-based detection first,
     * then Accept-Language header.
     */
    public static function resolveFromRequest(Request $request): ?string
    {
        $ip = $request->ip();
        if ($ip) {
            $code = static::resolveFromIp($ip, $request);
            if ($code) {
                return $code;
            }
        }

        $accept = $request->header('Accept-Language', '');
        if ($accept) {
            return static::resolveFromAcceptLanguage($accept);
        }

        return null;
    }

    /**
     * Detect the visitor's country from the client IP. Uses the Cloudflare
     * `CF-IPCountry` header when present, otherwise the free stevebauman/location
     * package (ip-api.com driver with fallbacks), cached per IP (a day on
     * success, a few minutes on failure). The ISO alpha-2 result is matched to
     * `iso_code_2` among the portal-selectable countries.
     *
     * The header and IP are only a convenience hint: the result is always
     * checked against the selectable countries and never grants anything.
     */
    public static function resolveFromIp(string $ip, ?Request $request = null): ?string
    {
        $request ??= request();
        $code = $request->header('CF-IPCountry');

        if (! is_string($code) || strlen($code) !== 2 || strtoupper($code) === 'XX') {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }

            $code = static::lookupIsoCode($ip);
        }

        return static::siteCodeForIso($code);
    }

    /**
     * Detect country from the Accept-Language header (e.g. "ar-OM,en;q=0.8" → "omn").
     * Matches each tag's two-letter region subtag, in the order sent, against
     * `iso_code_2` among the portal-selectable countries.
     */
    public static function resolveFromAcceptLanguage(string $acceptLanguage): ?string
    {
        foreach (explode(',', $acceptLanguage) as $entry) {
            $tag = trim(explode(';', $entry)[0] ?? '');
            $parts = explode('-', str_replace('_', '-', $tag));

            if (count($parts) < 2 || strlen($parts[1]) !== 2 || ! ctype_alpha($parts[1])) {
                continue;
            }

            if ($siteCode = static::siteCodeForIso($parts[1])) {
                return $siteCode;
            }
        }

        return null;
    }

    /**
     * site_code of the portal-selectable country with this ISO alpha-2 code.
     */
    public static function siteCodeForIso(?string $iso): ?string
    {
        if (! is_string($iso) || strlen($iso) !== 2) {
            return null;
        }

        return static::portalSelectable()
            ->where('iso_code_2', strtoupper($iso))
            ->value('site_code');
    }

    /**
     * ISO alpha-2 code for an IP via the geo provider, cached. Failures are
     * cached only briefly so a provider hiccup does not pin a visitor for a day.
     */
    private static function lookupIsoCode(string $ip): string
    {
        $key = "geo:country:{$ip}";

        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        try {
            $code = (string) (Location::get($ip)->countryCode ?? '');
        } catch (\Throwable) {
            $code = '';
        }

        Cache::put($key, $code, $code === '' ? self::GEO_FAILURE_CACHE_TTL : self::GEO_CACHE_TTL);

        return $code;
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function codSupermallCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'cod_supermall_category_id');
    }

    public function countryPaymentGateways(): HasMany
    {
        return $this->hasMany(CountryPaymentGateway::class);
    }

    public function countryShippingSettings(): HasMany
    {
        return $this->hasMany(CountryShippingSetting::class);
    }

    public function countryCategories(): HasMany
    {
        return $this->hasMany(CountryCategory::class);
    }

    public function productCountries(): HasMany
    {
        return $this->hasMany(ProductCountry::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function shippingZones(): HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }

    public function documentCountryRequirements(): HasMany
    {
        return $this->hasMany(VendorDocumentCountryRequirement::class);
    }

    // ── Document requirements helper ───────────────────────────────────────

    public function requiredDocumentTypesFor(): Collection
    {
        return VendorDocumentType::where('is_active', true)
            ->whereHas('countryRequirements', fn ($q) => $q->where('country_id', $this->id)
                ->where('requirement_level', '!=', 'not_applicable')
            )
            ->with(['countryRequirements' => fn ($q) => $q->where('country_id', $this->id),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($type) => [
                'type' => $type,
                'requirement_level' => $type->countryRequirements->first()->requirement_level,
            ]);
    }
}
