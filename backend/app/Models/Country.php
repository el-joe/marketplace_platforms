<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    /**
     * Resolve the effective site_code for {country?} portal routes.
     *
     * Resolution order:
     *  1. URL {country} route segment
     *  2. Session `portal_country` (manual picker / country switcher)
     *  3. IP-based detection (geoip extension or Accept-Language hint)
     *  4. Accept-Language header (e.g. "ar-AE" → AE)
     *  5. First active/launched country (data-driven, no hardcoded fallback)
     */
    public static function resolveSiteCode(?string $country): ?string
    {
        return $country
            ?? session('portal_country')
            ?? static::resolveFromRequest(request())
            ?? static::where('is_active', true)->where('is_launched', true)->value('site_code');
    }

    /**
     * Resolve site_code from the full HTTP request: IP-based detection first,
     * then Accept-Language header.
     */
    public static function resolveFromRequest(Request $request): ?string
    {
        $ip = $request->ip();
        if ($ip) {
            $code = static::resolveFromIp($ip);
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
     * Detect country from client IP address via the geoip PHP extension.
     * Returns null when the extension is unavailable or the IP cannot be mapped.
     */
    public static function resolveFromIp(string $ip): ?string
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.')) {
            return null;
        }

        if (function_exists('geoip_country_code_by_name')) {
            $code = @geoip_country_code_by_name($ip);
            if ($code) {
                return static::where('site_code', strtolower($code))
                    ->where('is_active', true)
                    ->where('is_launched', true)
                    ->value('site_code');
            }
        }

        return null;
    }

    /**
     * Detect country from the Accept-Language header (e.g. "ar-AE" → "ae").
     * Matches the two-letter region subtag against countries.site_code.
     */
    public static function resolveFromAcceptLanguage(string $acceptLanguage): ?string
    {
        $primary = explode(',', $acceptLanguage)[0] ?? '';
        $tag = explode(';', $primary)[0] ?? '';

        if (str_starts_with($tag, '-') || strlen($tag) < 3) {
            return null;
        }

        $parts = explode('-', $tag);
        if (count($parts) < 2) {
            return null;
        }

        $countryCode = strtolower($parts[1]);

        return static::where('site_code', $countryCode)
            ->where('is_active', true)
            ->where('is_launched', true)
            ->value('site_code');
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
