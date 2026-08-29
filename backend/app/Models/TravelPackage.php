<?php

namespace App\Models;

use App\Enums\TravelPackageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class TravelPackage extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'travel_agency_id',
        'slug',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'destination_country',
        'destination_city',
        'destination_travel_country_id',
        'destination_travel_city_id',
        'price',
        'currency',
        'pricing_tiers_enabled',
        'show_pricing_tiers_to_customer',
        'duration_days',
        'duration_nights',
        'departure_date',
        'return_date',
        'available_seats',
        'seats_booked',
        'status',
        'approved_by_admin_id',
        'approved_at',
        'rejection_reason',
        'contract_file_path',
        'contract_file_original_name',
        'contract_uploaded_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package) {
            $base = Str::slug($package->title_en ?? 'package');
            do {
                $slug = $base . '-' . Str::lower(Str::random(6));
            } while (static::where('slug', $slug)->exists());
            $package->slug = $slug;
        });

        static::saved(function (self $package) {
            if ($package->isDirty('status')) {
                \App\Services\Customer\UnifiedCategoryService::flushCache();

                if (\Illuminate\Support\Facades\Cache::supportsTags()) {
                    \Illuminate\Support\Facades\Cache::tags(['pages'])->flush();
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'approved_at' => 'datetime',
            'contract_uploaded_at' => 'datetime',
            'status' => TravelPackageStatus::class,
            'pricing_tiers_enabled' => 'boolean',
            'show_pricing_tiers_to_customer' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function agency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class, 'travel_agency_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TravelPackageMedia::class)->orderBy('position');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(TravelPackagePricingTier::class)->orderBy('travelers_count');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TravelBooking::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(TravelPackageInquiry::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TravelCategory::class, 'travel_package_categories');
    }

    public function inclusions(): BelongsToMany
    {
        return $this->belongsToMany(TravelInclusion::class, 'travel_package_inclusions', 'travel_package_id', 'travel_inclusion_id');
    }

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(TravelCountry::class, 'destination_travel_country_id');
    }

    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'destination_travel_city_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function priceFormatted(): string
    {
        return \App\Helpers\CurrencyFormatter::formatPrice($this->price, $this->currency);
    }

    /**
     * Total price for a given number of travelers: uses an exact-match
     * pricing tier when tiers are enabled and one exists for that count,
     * otherwise falls back to the flat price multiplied by the count.
     */
    public function priceForTravelersCount(int $travelersCount): int
    {
        if ($this->pricing_tiers_enabled) {
            $tier = $this->pricingTiers->firstWhere('travelers_count', $travelersCount);

            if ($tier) {
                return $tier->price;
            }
        }

        return $this->price * $travelersCount;
    }

    /**
     * Replaces this package's pricing tiers with the given set. Each item
     * must contain travelers_count and price.
     */
    public function syncPricingTiers(array $tiers): void
    {
        $this->pricingTiers()->delete();

        foreach (array_values($tiers) as $position => $tier) {
            $this->pricingTiers()->create([
                'travelers_count' => $tier['travelers_count'],
                'price' => $tier['price'],
                'position' => $position,
            ]);
        }
    }

    /**
     * Business rules for the draft → pending_review transition: description
     * copy, destination, contract, and at least one media item must be in
     * place, and the departure date must not have already passed while the
     * package sat in draft. Returns a list of human-readable errors; empty
     * means the package is ready to submit.
     */
    public function reviewReadinessErrors(): array
    {
        $errors = [];

        if (blank($this->description_en)) {
            $errors[] = 'An English description is required before submitting for review.';
        }

        if (blank($this->description_ar)) {
            $errors[] = 'An Arabic description is required before submitting for review.';
        }

        if (! $this->destination_travel_country_id) {
            $errors[] = 'A destination country is required before submitting for review.';
        }

        if (! $this->departure_date || $this->departure_date->isPast()) {
            $errors[] = 'The departure date must be in the future before submitting for review.';
        }

        if (! $this->contract_file_path) {
            $errors[] = 'A signed contract file must be uploaded before submitting for review.';
        }

        if ($this->media()->count() === 0) {
            $errors[] = 'At least one photo or video must be uploaded before submitting for review.';
        }

        return $errors;
    }

    public function seatsRemaining(): ?int
    {
        if ($this->available_seats === null) {
            return null;
        }

        return max(0, $this->available_seats - $this->seats_booked);
    }

    public function coverImage(): ?TravelPackageMedia
    {
        return $this->media->where('media_type', 'image')->first();
    }

    public function searchableAs(): string
    {
        return 'travel_packages';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['media', 'agency', 'categories']);

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'destination_country' => $this->destination_country,
            'destination_city' => $this->destination_city,
            'price' => $this->price,
            'departure_date' => $this->departure_date?->timestamp,
            'created_at' => $this->created_at?->timestamp,
            'thumbnail' => $this->media->first()?->url(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === TravelPackageStatus::Active;
    }
}
