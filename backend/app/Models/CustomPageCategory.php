<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomPageCategory extends Model
{
    use HasUuids;

    protected $table = 'custom_page_category_map';

    protected $fillable = [
        'custom_page_id',
        'category_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function customPage(): BelongsTo
    {
        return $this->belongsTo(CustomPage::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
