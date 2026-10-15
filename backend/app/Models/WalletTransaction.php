<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class WalletTransaction extends Model
{
    use HasUuids;

    protected $table = 'wallet_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'customer_id',
        'type',
        'direction',
        'amount',
        'balance_after',
        'currency_code',
        'reference_type',
        'reference_id',
        'source_type',
        'source_id',
        'description',
        'note',
        'performed_by_admin_id',
    ];

    protected $casts = [
        'amount' => DecimalMoney::class,
        'balance_after' => DecimalMoney::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('WalletTransaction records are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('WalletTransaction records are append-only and cannot be deleted.');
        });
    }

    /** Source types with a readable label (key under marketer.finance.tx_source.*). */
    public const SOURCE_LABELS = [
        'marketer_conversion' => 'commission',
        'marketer_commission' => 'commission',
        'marketer_commission_fee' => 'commission_fee',
        'marketer_campaign_fixed' => 'fixed',
        'marketer_campaign_fee' => 'campaign_fee',
    ];

    /** Readable label of a known source_type (commission, commission fee, influencer fixed amount, campaign fee), else null. */
    public function sourceLabel(): ?string
    {
        $key = self::SOURCE_LABELS[$this->source_type] ?? null;

        return $key ? __('marketer.finance.tx_source.'.$key) : null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeCredits($query)
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', 'debit');
    }

    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
