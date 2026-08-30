<?php

namespace App\Models;

use App\Enums\VendorSubscriptionInvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VendorSubscriptionInvoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'period_start',
        'period_end',
        'paid_at',
        'payment_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
            'status' => VendorSubscriptionInvoiceStatus::class,
        ];
    }

    // ── Boot: auto-generate invoice_number ────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->invoice_number)) {
                $year = now()->format('Y');
                $seq = static::whereYear('created_at', $year)->count() + 1;
                $model->invoice_number = sprintf('INV-SUB-%s-%05d', $year, $seq);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'subscription_id');
    }

    // ── Scopes / Helpers ──────────────────────────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', VendorSubscriptionInvoiceStatus::Paid);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', VendorSubscriptionInvoiceStatus::Open);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            VendorSubscriptionInvoiceStatus::Paid => 'success',
            VendorSubscriptionInvoiceStatus::Open => 'warning',
            VendorSubscriptionInvoiceStatus::Void => 'secondary',
            VendorSubscriptionInvoiceStatus::Uncollectible => 'danger',
        };
    }

    public function amountFormatted(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }
}
