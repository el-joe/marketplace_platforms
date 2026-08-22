<?php

namespace App\Models;

use App\Enums\BannerAudience;
use App\Enums\BannerDeviceTarget;
use App\Enums\BannerLinkType;
use App\Enums\BannerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Banner extends Model
{
    use HasUuids;

    protected $casts = [
        'status' => BannerStatus::class,
        'link_type' => BannerLinkType::class,
        'device_target' => BannerDeviceTarget::class,
        'audience' => BannerAudience::class,
    ];

    protected $fillable = [
        'country_id',
        'name',
        'placement_code',
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
        'impressions_count',
        'clicks_count',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
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
}
