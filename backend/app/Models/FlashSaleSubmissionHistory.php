<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleSubmissionHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'flash_sale_submission_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'changed_by_role',
        'reason',
    ];

    protected $casts = [
        'changed_by_role' => \App\Enums\FlashSaleSubmissionChangedByRole::class,
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FlashSaleSubmission::class, 'flash_sale_submission_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_user_id');
    }
}
