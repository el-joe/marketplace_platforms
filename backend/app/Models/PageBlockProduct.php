<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlockProduct extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'page_block_id',
        'tab_index',
        'product_variant_id',
        'position',
        'added_by_admin_id',
    ];

    protected $casts = [
        'tab_index' => 'integer',
        'position' => 'integer',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function addedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'added_by_admin_id');
    }
}
