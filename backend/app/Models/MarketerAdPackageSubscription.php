<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerAdPackageSubscription extends Model
{
    use HasUuids;

    protected $fillable = ['marketer_id', 'package_id', 'price', 'vat_pct', 'duration_days', 'amount_paid', 'vat_amount',
        'currency', 'payment_method', 'payment_proof_path', 'status', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'vat_pct' => 'integer', 'duration_days' => 'integer',
            'amount_paid' => 'integer', 'vat_amount' => 'integer',
            'starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** Active AND not past expiry (query-based check, independent of the scheduler). */
    public function scopeCurrentlyActive($q)
    {
        return $q->where('status', 'active')->where('expires_at', '>', now());
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MarketerAdPackage::class, 'package_id');
    }
}
