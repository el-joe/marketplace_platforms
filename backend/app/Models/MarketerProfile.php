<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id', 'banner_file_id', 'video_url',
        'bio_ar', 'bio_en', 'social_links', 'contact_details',
        'qr_code_path', 'profile_slug',
        'total_campaigns', 'total_conversions', 'total_earnings', 'earnings_currency',
    ];

    protected $casts = [
        'social_links' => 'array',
        'contact_details' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bannerFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'banner_file_id');
    }
}
