<?php

namespace App\Models;

use App\Enums\VendorGlobalStatus;
use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class Marketer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'whatsapp_for_campaigns',
        'global_status',
        'country_id',
        'approved_at',
        'approved_by_admin_id',
        'rejection_reason',
        'onboarding_completed_at',
        'last_login_at',
        'last_login_ip',
        'total_campaigns',
        'total_conversions',
        'total_earnings',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'total_earnings' => 'integer',
            'global_status' => VendorGlobalStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketerAdmins(): HasMany
    {
        return $this->hasMany(MarketerAdmin::class, 'marketer_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketerDocument::class);
    }

    public function needsOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }

    public function marketerProfile(): HasOne
    {
        return $this->hasOne(MarketerProfile::class, 'marketer_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MarketerCampaignInvitation::class, 'marketer_id');
    }

    public function campaignInvitations(): HasMany
    {
        return $this->hasMany(MarketerCampaignInvitation::class, 'marketer_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MarketerListing::class, 'marketer_id');
    }

    public function flashSaleInvitations(): HasMany
    {
        return $this->hasMany(FlashSaleMarketerInvitation::class);
    }

    public function categoryCommissions(): HasMany
    {
        return $this->hasMany(MarketerCategoryCommission::class);
    }

    public function classifiedListings(): MorphMany
    {
        return $this->morphMany(ClassifiedListing::class, 'seller');
    }

    public function exclusiveContracts(): HasMany
    {
        return $this->hasMany(ExclusiveContract::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(MarketerContract::class, 'marketer_id');
    }

    public function contractAcceptances(): HasMany
    {
        return $this->hasMany(MarketerContractAcceptance::class, 'marketer_id');
    }

    /**
     * enhancement.md P-16: a marketer must both be approved
     * (global_status = active) AND have accepted the CURRENT active
     * version of their onboarding contract before they can accept a
     * campaign invitation. If no contract has been set up for them at
     * all, there is nothing to accept, so they are not blocked by it.
     */
    public function hasAcceptedContract(): bool
    {
        $contract = $this->contract()->with('activeVersion')->first();

        if (! $contract || ! $contract->activeVersion) {
            return true;
        }

        return MarketerContractAcceptance::where('marketer_id', $this->id)
            ->whereNull('customer_id')
            ->where('marketer_contract_version_id', $contract->activeVersion->id)
            ->exists();
    }

    // ── Type helpers ───────────────────────────────────────────────────────

    public function isInfluencer(): bool
    {
        return $this->marketerJobs()->where('key', 'influencer')->exists();
    }

    public function isAffiliate(): bool
    {
        return $this->marketerJobs()->where('key', 'affiliate')->exists();
    }

    /**
     * Categories this marketer is scoped to for the given job + category source.
     * No scope rows means "all categories of that source".
     *
     * @return Collection<int, Model>
     */
    public function categoriesFor(string $jobKey, string $categoryType): Collection
    {
        $assignment = $this->marketerJobAssignments()
            ->whereHas('marketerJob', fn ($q) => $q->where('key', $jobKey))
            ->with('marketerJob')
            ->first();

        if (! $assignment) {
            return collect();
        }

        // The job itself may restrict which categories of this source are eligible at all.
        $jobEligible = $assignment->marketerJob->eligibleCategories($categoryType);

        $scopeIds = $assignment->categoryScopes()->where('category_type', $categoryType)->pluck('category_id');

        if ($scopeIds->isEmpty()) {
            return $jobEligible;
        }

        return $jobEligible->whereIn('id', $scopeIds)->values();
    }

    public function marketerJobs(): BelongsToMany
    {
        return $this->belongsToMany(MarketerJob::class, 'marketer_marketer_job')->withTimestamps();
    }

    public function marketerJobAssignments(): HasMany
    {
        return $this->hasMany(MarketerMarketerJob::class, 'marketer_id');
    }

    public function isActive(): bool
    {
        return $this->global_status?->value === 'active'
            || $this->global_status?->value === 'active';
    }

    public function isPending(): bool
    {
        return $this->global_status?->value === 'pending';
    }

    protected static function booted(): void
    {
        static::saved(function (self $marketer) {
            if ($marketer->wasChanged(['name', 'global_status', 'total_campaigns', 'total_conversions'])) {
                MarketerProfileCache::bump($marketer->marketerProfile()->value('profile_slug'));
            }
        });
    }
}
