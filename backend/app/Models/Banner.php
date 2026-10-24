<?php

namespace App\Models;

use App\Enums\BannerAudience;
use App\Enums\BannerDeviceTarget;
use App\Enums\BannerGenderScope;
use App\Enums\BannerLinkType;
use App\Enums\BannerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Banner extends Model
{
    use HasFactory;
    use HasUuids;

    /** Placements that support Global / selected-marketers targeting. */
    public const MARKETER_PLACEMENTS = ['marketers_list_hero', 'marketers_list_strip', 'marketer_profile_slider'];

    protected $casts = [
        'status' => BannerStatus::class,
        'link_type' => BannerLinkType::class,
        'device_target' => BannerDeviceTarget::class,
        'audience' => BannerAudience::class,
        'is_global' => 'boolean',
        'gender_scope' => BannerGenderScope::class,
    ];

    protected $fillable = [
        'country_id',
        'name',
        'placement_code',
        'product_id',
        'media_id',
        'mobile_media_id',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'cta_label_en',
        'cta_label_ar',
        'cta_url',
        'link_type',
        'link_reference_id',
        'starts_at',
        'ends_at',
        'status',
        'device_target',
        'audience',
        'priority',
        'is_global',
        'gender_scope',
        'impressions_count',
        'clicks_count',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function isMarketerPlacement(): bool
    {
        return in_array($this->placement_code, self::MARKETER_PLACEMENTS, true);
    }

    public function marketers(): BelongsToMany
    {
        return $this->belongsToMany(Marketer::class, 'banner_marketer')->withTimestamps();
    }

    /**
     * Currently live banners: active status, inside the schedule window and valid for the country.
     */
    public function scopeLive(Builder $query, ?string $countryId = null): Builder
    {
        $now = now();

        return $query
            ->where('status', BannerStatus::Active->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(fn (Builder $q) => $q->whereNull('country_id')->orWhere('country_id', $countryId));
    }

    /**
     * Global banners for the marketers list page. A null gender (both tabs) keeps every
     * global banner; 'male' / 'female' keep banners scoped to that gender or to 'all'.
     */
    public function scopeForMarketerList(Builder $query, ?string $gender = null): Builder
    {
        $query->where('is_global', true);

        if ($gender !== null) {
            $query->whereIn('gender_scope', [BannerGenderScope::All->value, $gender]);
        }

        return $query;
    }

    /**
     * Banners shown inside a marketer's details page: global banners whose gender scope matches
     * the marketer (gender-less marketers only get 'all'), plus non-global banners selected for them.
     */
    public function scopeForMarketerProfile(Builder $query, Marketer $marketer): Builder
    {
        $gender = $marketer->relationLoaded('marketerProfile')
            ? $marketer->marketerProfile?->gender
            : $marketer->marketerProfile()->value('gender');
        $gender = $gender instanceof \BackedEnum ? $gender->value : $gender;

        return $query->where(function (Builder $q) use ($marketer, $gender) {
            $q->where(function (Builder $g) use ($gender) {
                $g->where('is_global', true)
                    ->whereIn('gender_scope', array_filter([BannerGenderScope::All->value, $gender]));
            })->orWhere(function (Builder $s) use ($marketer) {
                $s->where('is_global', false)
                    ->whereHas('marketers', fn (Builder $m) => $m->whereKey($marketer->getKey()));
            });
        });
    }
}
