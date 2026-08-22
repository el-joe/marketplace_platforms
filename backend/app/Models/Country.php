<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'launched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_launched' => 'boolean',
        'cod_available' => 'boolean',
        'vat_rate' => 'decimal:2',
        'launched_at' => 'datetime',
    ];

    /**
     * Resolve the effective site_code for {country?} portal routes: the URL segment
     * takes priority, then the header dropdown's session pick, then the first
     * active/launched country as a last-resort default.
     */
    public static function resolveSiteCode(?string $country): string
    {
        return $country
            ?? session('portal_country')
            ?? static::where('is_active', true)->where('is_launched', true)->value('site_code')
            ?? 'ae';
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
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

    public function requiredDocumentTypesFor(): \Illuminate\Support\Collection
    {
        return VendorDocumentType::where('is_active', true)
            ->whereHas('countryRequirements', fn ($q) =>
                $q->where('country_id', $this->id)
                  ->where('requirement_level', '!=', 'not_applicable')
            )
            ->with(['countryRequirements' => fn ($q) =>
                $q->where('country_id', $this->id)
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($type) => [
                'type'              => $type,
                'requirement_level' => $type->countryRequirements->first()->requirement_level,
            ]);
    }
}
