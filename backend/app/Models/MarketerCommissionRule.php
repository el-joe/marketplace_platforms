<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\CommissionVertical;
use App\Enums\MarketerType;
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
        'commission_min_amount',
        'rule_key', 'excluded_category_ids',
        'marketer_type', 'fee_percentage', 'fee_fixed',
    ];

    protected static function booted(): void
    {
        // NULL marketer/category can't be covered by a plain composite unique
        // index (NULLs are distinct), so a deterministic key carries the
        // uniqueness instead.
        static::saving(function (self $rule) {
            $rule->rule_key = self::makeRuleKey($rule->marketer_id, $rule->scope, $rule->category_type, $rule->category_id);
            $rule->enforceMarketerType();
        });
    }

    /**
     * Type constraints (v3): influencer = fixed amount only, affiliate = percentage only, broker = any.
     * Legacy rows (marketer_type NULL) are left untouched.
     */
    public function enforceMarketerType(): void
    {
        $type = $this->marketer_type ? MarketerType::tryFrom((string) $this->marketer_type) : null;
        if (! $type) {
            return;
        }

        if (! $type->allowsPercentage()) {
            $this->commission_rate = 0;
            $this->commission_mode = 'fixed';
        }
        if (! $type->allowsFixed()) {
            $this->commission_flat_amount = null;
            $this->commission_mode = 'percentage';
        }
    }

    /** Commission vertical a rule scope belongs to (products -> product, open_market -> classified, travel -> travel). */
    public static function verticalFor(string $scope): CommissionVertical
    {
        return match ($scope) {
            self::SCOPE_OPEN_MARKET => CommissionVertical::Classified,
            self::SCOPE_TRAVEL => CommissionVertical::Travel,
            default => CommissionVertical::Product,
        };
    }

    public static function scopeFor(CommissionVertical $vertical): string
    {
        return match ($vertical) {
            CommissionVertical::Classified => self::SCOPE_OPEN_MARKET,
            CommissionVertical::Travel => self::SCOPE_TRAVEL,
            CommissionVertical::Product => self::SCOPE_PRODUCTS,
        };
    }

    public static function makeRuleKey(?string $marketerId, string $scope, ?string $categoryType, ?string $categoryId): string
    {
        return sha1(implode('|', [$marketerId ?? 'platform', $scope, $categoryType ?? '-', $categoryId ?? 'default']));
    }

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_flat_amount' => DecimalMoney::class,
        'commission_min_amount' => DecimalMoney::class,
        'fee_percentage' => 'decimal:4',
        'fee_fixed' => DecimalMoney::class,
        'excluded_category_ids' => 'array',
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

    /** True when a scope-default rule is configured to skip any of these category ids (category + ancestors). */
    public function excludes(array $categoryChain): bool
    {
        return $this->category_id === null
            && ! empty($this->excluded_category_ids)
            && array_intersect($this->excluded_category_ids, $categoryChain) !== [];
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
     * floored to 2 decimals, or rounded to 2 decimals when $round is true.
     * Flat is per unit. An optional commission_min_amount is a per-unit floor
     * on the final amount.
     */
    public function resolveAmount(int|float|string $base, int $quantity = 1, bool $round = false): float
    {
        $mode = $this->commission_mode ?: 'percentage';
        $percent = 0.0;
        $flat = 0.0;

        if (in_array($mode, ['percentage', 'both'], true) && (float) $this->commission_rate > 0) {
            $raw = bcdiv(bcmul((string) $base, (string) $this->commission_rate, 4), '100', 4);
            $percent = $round ? round((float) $raw, 2) : floor(round((float) $raw * 100, 4)) / 100;
        }
        if (in_array($mode, ['fixed', 'both'], true)) {
            $flat = (float) ($this->commission_flat_amount ?? 0) * max(1, $quantity);
        }

        $calculated = round($percent + $flat, 2);

        $minCommission = (float) ($this->commission_min_amount ?? 0);
        if ($minCommission > 0) {
            $calculated = max($calculated, $minCommission * max(1, $quantity));
        }

        return $calculated;
    }
}
