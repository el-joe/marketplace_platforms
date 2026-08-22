<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayWebhookLog extends Model
{
    use HasUuids;

    protected $table = 'payment_gateway_webhook_logs';
    protected $keyType = 'string';
    public $incrementing = false;

    // Append-only — no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'country_payment_gateway_id',
        'gateway_code',
        'event_type',
        'payload',
        'headers',
        'signature_valid',
        'processed',
        'processing_error',
        'payment_transaction_id',
    ];

    protected $casts = [
        'payload'          => 'array',
        'headers'          => 'array',
        'signature_valid'  => 'boolean',
        'processed'        => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Append-only: prevent updates and deletes
        static::updating(fn() => throw new \LogicException('WebhookLog records are append-only.'));
        static::deleting(fn() => throw new \LogicException('WebhookLog records are append-only.'));
    }

    public function countryGateway(): BelongsTo
    {
        return $this->belongsTo(CountryPaymentGateway::class, 'country_payment_gateway_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
