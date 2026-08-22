<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CountryPaymentGateway extends Model
{
    use HasUuids;

    protected $table = 'country_payment_gateways';

    protected $fillable = [
        'country_id', 'gateway_id',
        'display_name_en', 'display_name_ar',
        'is_active', 'environment',
        'fee_pct', 'fee_fixed', 'min_order', 'max_order',
        'sort_order',
        'last_verified_at', 'last_verification_status', 'last_verification_message',
    ];

    // credentials_encrypted and webhook_secret_encrypted excluded from fillable
    // — always set via setCredentials() / setWebhookSecret()
    protected $hidden = [
        'credentials_encrypted',
        'webhook_secret_encrypted',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'fee_pct'             => 'decimal:2',
        'fee_fixed'           => 'integer',
        'min_order'           => 'integer',
        'max_order'           => 'integer',
        'sort_order'          => 'integer',
        'last_verified_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentGatewayWebhookLog::class, 'country_payment_gateway_id');
    }

    // ── Credential accessors (encrypted at rest) ──────────────────────────

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials));
        $this->save();
    }

    public function getCredentials(): array
    {
        if (!$this->credentials_encrypted) {
            return [];
        }
        try {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?? [];
        } catch (\Exception $e) {
            report($e);
            return [];
        }
    }

    public function setWebhookSecret(string $secret): void
    {
        $this->webhook_secret_encrypted = Crypt::encryptString($secret);
        $this->save();
    }

    public function getWebhookSecret(): ?string
    {
        if (!$this->webhook_secret_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->webhook_secret_encrypted);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    // ── Computed accessors ────────────────────────────────────────────────

    public function getIsConfiguredAttribute(): bool
    {
        $required = $this->gateway?->required_fields ?? [];
        if (empty($required)) {
            return true; // COD, wallet — no credentials needed
        }
        $stored = $this->getCredentials();
        foreach ($required as $field) {
            if (empty($stored[$field['key']])) {
                return false;
            }
        }
        return true;
    }

    public function getEffectiveCurrencyAttribute(): string
    {
        return $this->country?->currency_code ?? 'USD';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, string $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeByGatewayCode($query, string $code)
    {
        return $query->whereHas('gateway', fn($q) => $q->where('code', $code));
    }
}
