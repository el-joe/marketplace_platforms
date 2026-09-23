<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MarketerCommissionRule extends Model
{
    use HasUuids;

    public const SCOPE_PRODUCTS = 'products';
    public const SCOPE_OPEN_MARKET = 'open_market';
    public const SCOPE_TRAVEL = 'travel';
    public const SCOPES = [self::SCOPE_PRODUCTS, self::SCOPE_OPEN_MARKET, self::SCOPE_TRAVEL];

    protected $fillable = [
        'marketer_id', 'scope', 'category_type', 'category_id',
        'commission_mode', 'commission_rate', 'commission_flat_amount', 'updated_by_admin_id',
        'rule_key',
    ];

    protected static function booted(): void
    {
        // NULL marketer/category can't be covered by a plain composite unique
        // index (NULLs are distinct), so a deterministic key carries the
        // uniqueness instead.
        static::saving(function (self $rule) {
            $rule->rule_key = self::makeRuleKey($rule->marketer_id, $rule->scope, $rule->category_type, $rule->category_id);
        });
    }

    public static function makeRuleKey(?string $marketerId, string $scope, ?string $categoryType, ?string $categoryId): string
    {
        return sha1(implode('|', [$marketerId ?? 'platform', $scope, $categoryType ?? '-', $categoryId ?? 'default']));
    }

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_flat_amount' => 'integer',
    ];

    /** Category model class for a scope. */
    public static function categoryClassFor(string $scope): string
    {
        return match ($scope) {
            self::SCOPE_OPEN_MARKET => ClassifiedCategory::class,
            self::SCOPE_TRAVEL => TravelCategory::class,
            default => Category::class,
        };
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function category(): MorphTo
    {
        return $this->morphTo();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    /**
     * fixed = flat*qty; percentage = base*rate%; both = sum. Percentage is
     * floored, or rounded when $round is true. Flat is per unit.
     */
    public function resolveAmount(int|string $base, int $quantity = 1, bool $round = false): int
    {
        $mode = $this->commission_mode ?: 'percentage';
        $percent = 0;
        $flat = 0;

        if (in_array($mode, ['percentage', 'both'], true) && (float) $this->commission_rate > 0) {
            $raw = bcdiv(bcmul((string) $base, (string) $this->commission_rate, 4), '100', 4);
            $percent = $round ? (int) round((float) $raw) : (int) floor((float) $raw);
        }
        if (in_array($mode, ['fixed', 'both'], true)) {
            $flat = (int) ($this->commission_flat_amount ?? 0) * max(1, $quantity);
        }

        return $percent + $flat;
    }
}
