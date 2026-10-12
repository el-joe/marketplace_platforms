<?php

namespace App\Models;

use App\Enums\PromotionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fine for closing a month below the promotion minimum. One row per marketer/category/month. */
class MarketerPromotionFine extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const DEDUCTED = 'deducted';

    public const WAIVED = 'waived';

    protected $fillable = [
        'marketer_id', 'promotion_category', 'period', 'target', 'done', 'amount', 'currency',
        'status', 'wallet_transaction_id', 'deducted_at', 'waived_by_admin_id', 'note',
    ];

    protected $casts = [
        'promotion_category' => PromotionCategory::class,
        'target' => 'integer',
        'done' => 'integer',
        'amount' => 'integer',
        'deducted_at' => 'datetime',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }
}
