<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedListingAttachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'classified_listing_id', 'attachment_type',
        'file_path', 'status', 'verified_by_admin_id',
    ];

    protected $casts = [
        'status' => \App\Enums\ClassifiedListingAttachmentStatus::class,
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }
}
