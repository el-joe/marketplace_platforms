<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Crypt;

class ShippingCarrier extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipping_company_id',
        'country_id',
        'name',
        'code',
        'api_endpoint',
        'credentials_encrypted',
        'tracking_url_pattern',
        'supports_cod',
        'supports_returns',
        'is_active',
    ];

    protected $casts = [
        'supports_cod' => 'boolean',
        'supports_returns' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @var string[] Never expose credentials in JSON */
    protected $hidden = ['credentials_encrypted'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'carrier_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'carrier_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    // ── Credential helpers ────────────────────────────────────────────────────

    public function getDecryptedCredentials(): array
    {
        if ($this->credentials_encrypted) {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?? [];
        }

        return [];
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials));
        $this->save();
    }
}
