<?php

namespace App\Models;

use App\Enums\PayoutSchedule;
use App\Enums\VendorBusinessType;
use App\Enums\VendorGlobalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, HasUuids/*, SoftDeletes*/ ;
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'phone_verified_at',
        'password',
        'store_name',
        'store_slug',
        'store_description',
        'business_name',
        'business_type',
        'business_registration_number',
        'tax_id',
        'contact_email',
        'contact_phone',
        'whatsapp_number',
        'business_address_id',
        'commission_rate',
        'default_warehouse_id',
        'payout_schedule',
        'payout_hold_active',
        'payout_hold_reason',
        'store_rating_avg',
        'store_rating_count',
        'total_sales',
        'total_orders',
        'return_rate_pct',
        'cancellation_rate_pct',
        'sla_compliance_pct',
        'strikes_count',
        'country_id',
        // 'status',
        'last_login_at',
        'last_login_ip',
        'approved_at',
        'approved_by_admin_id',
        'avatar',
        'global_status',
        'onboarding_completed_at',
        'rejection_reason',
        'account_manager_admin_id',
        'warranty_months',
        'easy_returns_enabled',
        'secure_payments_enabled',
        'marketer_type',
        'whatsapp_for_campaigns',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'payout_hold_active' => 'boolean',
            'store_rating_avg' => 'float',
            'total_sales' => 'float',
            'return_rate_pct' => 'float',
            'cancellation_rate_pct' => 'float',
            'strikes_count' => 'integer',
            'global_status' => VendorGlobalStatus::class,
            'business_type' => VendorBusinessType::class,
            'payout_schedule' => PayoutSchedule::class,
            'warranty_months' => 'integer',
            'easy_returns_enabled' => 'boolean',
            'secure_payments_enabled' => 'boolean',
        ];
    }

    public function getPositiveRatingPctAttribute(): ?int
    {
        return $this->store_rating_avg > 0 ? (int) round(($this->store_rating_avg / 5) * 100) : null;
    }

    public function getPartnerYearsAttribute(): ?int
    {
        return $this->created_at?->diffInYears(now());
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function businessAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'business_address_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(VendorStrike::class);
    }

    public function activeStrikes(): HasMany
    {
        return $this->hasMany(VendorStrike::class)->where('is_active', true);
    }

    public function vendorAdmins(): HasMany
    {
        return $this->hasMany(VendorAdmin::class);
    }

    public function accountManagerAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'account_manager_admin_id');
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(\App\Models\SubOrder::class);
    }

    public function acquisitionCommissions(): HasMany
    {
        return $this->hasMany(VendorAcquisitionCommission::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'owner_vendor_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(VendorListing::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(VendorBankAccount::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function cityShippingSurcharges(): HasMany
    {
        return $this->hasMany(VendorCityShippingSurcharge::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function classifiedListings(): MorphMany
    {
        return $this->morphMany(ClassifiedListing::class, 'seller');
    }

    public function sectionLocks(): HasMany
    {
        return $this->hasMany(VendorSectionLock::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(VendorChangeRequest::class);
    }

    public function marketerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MarketerProfile::class);
    }

    public function campaignsAsVendor(): HasMany
    {
        return $this->hasMany(MarketerCampaign::class);
    }

    public function campaignInvitations(): HasMany
    {
        return $this->hasMany(MarketerCampaignInvitation::class, 'marketer_vendor_id');
    }

    public function isMarketer(): bool
    {
        return $this->marketer_type !== null;
    }

    public function isInfluencer(): bool
    {
        return $this->marketer_type === 'influencer';
    }

    public function isAffiliate(): bool
    {
        return $this->marketer_type === 'affiliate';
    }
}
