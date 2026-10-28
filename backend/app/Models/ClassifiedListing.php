<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\ClassifiedListingAttachmentStatus;
use App\Enums\ClassifiedListingStatus;
use App\Helpers\CurrencyFormatter;
use App\Services\Customer\UnifiedCategoryService;
use App\Services\MarketerCampaignService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassifiedListing extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'listing_number', 'slug', 'seller_type', 'seller_id',
        'classified_category_id', 'country_id', 'city_id', 'listing_purpose',
        'title_en', 'title_ar', 'description_en', 'description_ar',
        'price', 'currency', 'price_negotiable', 'attributes',
        'latitude', 'longitude', 'sketch_file_path', 'status',
        'contract_template_id', 'contract_accepted_at', 'contract_signature_data',
        'rejection_reason', 'approved_by_admin_id', 'approved_at',
        'views_count', 'expires_at', 'barcode_path',
        'is_vendor_listing', 'vendor_listing_reference',
        'sold_price', 'sold_at', 'sold_via_campaign_id', 'sold_buyer_note', 'campaign_enabled',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'attributes' => 'array',
        'price' => DecimalMoney::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'contract_accepted_at' => 'datetime',
        'approved_at' => 'datetime',
        'sold_price' => DecimalMoney::class,
        'sold_at' => 'datetime',
        'expires_at' => 'date',
        'price_negotiable' => 'boolean',
        'is_vendor_listing' => 'boolean',
        'campaign_enabled' => 'boolean',
        'status' => ClassifiedListingStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $listing) {
            $base = Str::slug($listing->title_en ?? 'listing');
            $suffix = 'clsf-'.Str::lower($listing->listing_number ?? Str::random(6));
            $listing->slug = $base.'-'.$suffix;
        });

        static::saving(function (self $listing) {
            // is_vendor_listing derives from seller_type — never set it independently
            $listing->is_vendor_listing = $listing->seller_type === Vendor::class;
        });

        static::saved(function (self $listing) {
            if ($listing->isDirty('status')) {
                UnifiedCategoryService::flushCache();
            }
        });
    }

    public function seller(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCustomers($query)
    {
        return $query->where('seller_type', Customer::class);
    }

    public function scopeForVendors($query)
    {
        return $query->where('seller_type', Vendor::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Listings whose coordinates fall within $radiusKm of a point: a bounding box (index-friendly)
     * narrowed by the exact great-circle distance.
     *
     * @param  Builder<ClassifiedListing>  $query
     * @return Builder<ClassifiedListing>
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusKm): Builder
    {
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->whereRaw(
                '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) <= ?',
                [$lat, $lng, $lat, $radiusKm],
            );
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class, 'contract_template_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClassifiedListingAttachment::class, 'classified_listing_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ClassifiedListingImage::class, 'classified_listing_id')
            ->orderBy('position');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(ClassifiedInquiry::class, 'classified_listing_id');
    }

    public function getPriceFormattedAttribute(): string
    {
        return CurrencyFormatter::formatPrice($this->price, $this->currency);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $primary = $this->images->firstWhere('is_primary', true)
                    ?? $this->images->first();

        return $primary ? Storage::url($primary->file_path) : null;
    }

    public function getRequiredAttachmentsCompleteAttribute(): bool
    {
        $required = $this->classifiedCategory?->required_attachment_types ?? [];
        if (empty($required)) {
            return true;
        }

        // If the seller uploaded no attachments at all, skip the check —
        // attachments are optional at submission; the guard only blocks
        // when attachments exist but some are still unverified.
        if ($this->attachments->isEmpty()) {
            return true;
        }

        $verified = $this->attachments
            ->where('status', ClassifiedListingAttachmentStatus::Verified)
            ->pluck('attachment_type')
            ->toArray();

        return empty(array_diff($required, $verified));
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->title_ar : $this->title_en;
    }

    /** Campaign through which this listing was sold (set when marked sold with an attributed marketer). */
    public function soldViaCampaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'sold_via_campaign_id');
    }

    /** Marketer campaigns promoting this listing. */
    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketerCampaign::class, 'classified_listing_id');
    }

    /** The campaign currently open to marketers (live or awaiting review), if any. */
    public function openCampaigns(): HasMany
    {
        return $this->campaigns()->whereNotIn('status', MarketerCampaignService::TERMINAL_STATUSES);
    }
}
