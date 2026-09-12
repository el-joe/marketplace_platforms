<?php

namespace App\Models;

use App\Enums\PaidAdSlotPricingModel;
use App\Enums\PaidAdSlotTargetType;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaidAdSlot extends Model
{
    use HasUuids;
    use SoftDeletes;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'pricing_model' => PaidAdSlotPricingModel::class,
        'target_type' => PaidAdSlotTargetType::class,
        'is_available' => 'boolean',
        'requires_approval' => 'boolean',
        'shows_popup' => 'boolean',
        'min_booking_days' => 'integer',
        'max_booking_days' => 'integer',
        'item_position' => 'integer',
        'creative_width_px' => 'integer',
        'creative_height_px' => 'integer',
        'mobile_width_px' => 'integer',
        'mobile_height_px' => 'integer',
        'max_concurrent' => 'integer',
        'lead_time_days' => 'integer',
        'min_budget' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'target_type',
        'shows_popup',
        'placement_definition_id',
        'page_block_id',
        'item_position',
        'bound_item_id',
        'fill_mode',
        'country_id',
        'category_id',
        'name',
        'name_ar',
        'slot_code',
        'pricing_model',
        'base_rate',
        'currency',
        'min_booking_days',
        'max_booking_days',
        'is_available',
        'requires_approval',
        'notes_for_vendors',
        'notes_for_vendors_ar',
        'creative_width_px',
        'creative_height_px',
        'mobile_width_px',
        'mobile_height_px',
        'max_concurrent',
        'lead_time_days',
        'min_budget',
        'allowed_advertisers',
        'sort_order',
        'created_by_admin_id',
    ];

    public function placementDefinition(): BelongsTo
    {
        return $this->belongsTo(BannerPlacementDefinition::class, 'placement_definition_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(PaidAdBooking::class, 'paid_ad_slot_id');
    }

    /**
     * Creative dimension/format requirements: slot overrides win, then the
     * placement definition. page_block slots have no placement definition, so
     * an admin must set the overrides directly on the slot.
     */
    public function creativeSpec(): array
    {
        $placement = $this->target_type === PaidAdSlotTargetType::Placement
            ? $this->placementDefinition
            : null;

        $desktopW = $this->creative_width_px ?? $placement?->width_px;
        $desktopH = $this->creative_height_px ?? $placement?->height_px;
        $mobileW = $this->mobile_width_px ?? $placement?->mobile_width_px;
        $mobileH = $this->mobile_height_px ?? $placement?->mobile_height_px;
        $maxKb = $placement?->max_file_size_kb;
        $formats = $placement?->allowed_formats;

        if ($desktopW === null || $desktopH === null) {
            throw new DomainException(
                "Ad slot [{$this->id}] has no creative dimensions — set creative_width_px/creative_height_px on the slot."
            );
        }

        return [
            'desktop' => ['w' => $desktopW, 'h' => $desktopH],
            'mobile' => ['w' => $mobileW ?? $desktopW, 'h' => $mobileH ?? $desktopH],
            'max_kb' => $maxKb,
            'formats' => $formats ?? ['jpg', 'jpeg', 'png', 'webp'],
        ];
    }

    public function allowsAdvertiser(string $type): bool
    {
        return $this->allowed_advertisers === 'both' || $this->allowed_advertisers === $type;
    }

    public function scopeBookable($query)
    {
        return $query->where('is_available', true);
    }
}
