<?php

namespace App\Models;

use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketerListing extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'marketer_id',
        'product_variant_id',
        'country_id',
        'invitation_id',
        'price',
        'compare_at_price',
        'currency',
        'status',
        'condition',
        'score',
        'score_calculated_at',
        'referral_code',
        'referral_link',
        'total_sold',
        'rating_avg',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'price'               => 'integer',
            'compare_at_price'    => 'integer',
            'score'               => 'float',
            'score_calculated_at' => 'datetime',
            'rating_avg'          => 'float',
            'total_sold'          => 'integer',
            'rating_count'        => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class, 'invitation_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->whereNull('deleted_at');
    }

    public function scopeForCountry($q, string $countryId)
    {
        return $q->where('country_id', $countryId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected static function booted(): void
    {
        $bump = function (self $listing) {
            $slug = MarketerProfile::where('marketer_id', $listing->marketer_id)->value('profile_slug');
            MarketerProfileCache::bump($slug);
        };

        static::saved($bump);
        static::deleted($bump);
    }
}
