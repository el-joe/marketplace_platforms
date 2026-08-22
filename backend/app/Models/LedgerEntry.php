<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_group_id',
        'account_type',
        'account_holder_type',
        'account_holder_id',
        'debit',
        'credit',
        'currency',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'debit' => 'integer',
        'credit' => 'integer',
    ];

    /**
     * Append-only — financial ledger must never be modified.
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw new \RuntimeException('LedgerEntry is append-only and cannot be updated.');
    }

    /**
     * Append-only — financial ledger must never be deleted.
     */
    public function delete(): never
    {
        throw new \RuntimeException('LedgerEntry is append-only and cannot be deleted.');
    }

    public function scopeForGroup($query, string $groupId)
    {
        return $query->where('transaction_group_id', $groupId);
    }

    /**
     * Polymorphic owner (Vendor or Customer).
     * Uses manual columns: account_holder_type / account_holder_id.
     */
    public function accountHolder(): MorphTo
    {
        return $this->morphTo('account_holder');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }
}
