<?php

namespace App\Models;

use App\Enums\ClassifiedPlacement;
use App\Models\Concerns\HasContractRequirement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A classified section. Top-level categories are the storefront sections (Real Estate, Vehicles, ...); their
 * children are sub-categories. Which surfaces a section appears on (`placements`), in which countries
 * (`countries`, empty = all) and its image are configured by the admin.
 *
 * Deactivating a category (`is_active = false`) hides it and everything below it from the storefront. Nothing is
 * deleted: listings keep their status and owners, signed contracts stay as they are, and re-activating the
 * category brings everything back. Hard deletion is only possible for an unused category (see deletionBlockers()).
 */
class ClassifiedCategory extends Model
{
    use HasContractRequirement, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushStorefrontCaches());
        static::deleted(fn () => static::flushStorefrontCaches());
    }

    /**
     * Every cache that holds classified category data (navigation trees, section visibility). Also call it
     * after changing the country pivot, which does not fire model events.
     */
    public static function flushStorefrontCaches(): void
    {
        \App\Services\Customer\CategoryService::flushCache();
        \App\Services\Customer\UnifiedCategoryService::flushCache();
    }

    protected $fillable = [
        'name_en', 'name_ar', 'slug', 'icon', 'placements', 'description_en', 'description_ar', 'parent_id',
        'requires_location_map', 'requires_sketch_upload', 'attribute_schema',
        'contract_template_id', 'required_attachment_types',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'required_attachment_types' => 'array',
        'placements'                => 'array',
        'attribute_schema'          => 'array',
        'requires_location_map'     => 'boolean',
        'requires_sketch_upload'    => 'boolean',
        'is_active'                 => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contractAssignmentColumn(): string
    {
        return 'classified_category_id';
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ClassifiedListing::class, 'classified_category_id');
    }

    /**
     * Countries this category is offered in. No rows means every country.
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'classified_category_country')->withTimestamps();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(File::class, 'model')->where('is_primary', true)->orderBy('position');
    }

    public function getImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('primaryImage') ? $this->primaryImage : $this->primaryImage()->first();

        return $file?->full_path;
    }

    /**
     * The surfaces this section appears on. Categories saved before placements existed keep the old behaviour
     * (navigation + landing page).
     *
     * @return list<string>
     */
    public function placementList(): array
    {
        $placements = $this->placements;

        if ($placements === null) {
            return ClassifiedPlacement::legacyDefault();
        }

        return array_values(array_intersect(ClassifiedPlacement::values(), $placements));
    }

    public function appearsOn(ClassifiedPlacement $placement): bool
    {
        return in_array($placement->value, $this->placementList(), true);
    }

    /**
     * Why this category cannot be hard-deleted. Empty means it is unused and may be deleted. Records that would
     * be lost or that the database protects (signed contracts, enrolments) always block: deactivate instead.
     *
     * @return array<string, int> reason key (a `classified.admin.blocker_*` translation) => number of records
     */
    public function deletionBlockers(): array
    {
        $counts = [
            'children' => $this->children()->count(),
            'listings' => $this->listings()->count(),
            'wanted' => ClassifiedWantedListing::where('classified_category_id', $this->id)->count(),
            'signatures' => VendorContract::where('classified_category_id', $this->id)->count(),
            'enrollments' => VendorCategoryEnrollment::where('classified_category_id', $this->id)->count(),
            'exclusive' => ExclusiveContract::where('classified_category_id', $this->id)->count(),
            'prices' => OpenMarketListingPrice::where('classified_category_id', $this->id)->count(),
        ];

        return array_filter($counts);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
