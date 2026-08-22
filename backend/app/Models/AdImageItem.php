<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;

class AdImageItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_block_id',
        'position',
        'file_id',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'badge_label_en',
        'badge_label_ar',
        'link_url',
        'link_open_new_tab',
        'alt_text_en',
        'alt_text_ar',
        'show_title_overlay',
        'aspect_ratio',
        'is_active',
        'is_paid',
    ];

    protected $casts = [
        'link_open_new_tab' => 'boolean',
        'show_title_overlay' => 'boolean',
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'position' => 'integer',
    ];

    protected $appends = ['file_url'];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        $f = $this->file;
        return $f ? \Illuminate\Support\Facades\Storage::disk($f->storage_type)->url($f->path) : null;
    }
}
