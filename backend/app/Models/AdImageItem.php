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
        'file_id_en',
        'file_id_ar',
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

    protected $appends = ['file_url', 'file_url_en', 'file_url_ar'];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function fileEn(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id_en');
    }

    public function fileAr(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id_ar');
    }

    protected function fileUrl(?File $file): ?string
    {
        return $file ? \Illuminate\Support\Facades\Storage::disk($file->storage_type)->url($file->path) : null;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->getFileUrlEnAttribute();
    }

    public function getFileUrlEnAttribute(): ?string
    {
        return $this->fileUrl($this->fileEn);
    }

    public function getFileUrlArAttribute(): ?string
    {
        return $this->fileUrl($this->fileAr);
    }
}
