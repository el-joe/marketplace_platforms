<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;

class SliderSlide extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_block_id',
        'position',
        'desktop_file_id',
        'mobile_file_id',
        'desktop_file_id_en',
        'desktop_file_id_ar',
        'mobile_file_id_en',
        'mobile_file_id_ar',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'cta_label_en',
        'cta_label_ar',
        'cta_url',
        'cta_open_new_tab',
        'text_color',
        'text_position',
        'overlay_opacity',
        'link_type',
        'link_reference_id',
        'is_active',
        'is_paid',
        'visible_from',
        'visible_until',
    ];

    protected $casts = [
        'cta_open_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'overlay_opacity' => 'decimal:2',
        'position' => 'integer',
        'visible_from' => 'datetime',
        'visible_until' => 'datetime',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function desktopFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'desktop_file_id');
    }

    public function mobileFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'mobile_file_id');
    }

    public function desktopFileEn(): BelongsTo
    {
        return $this->belongsTo(File::class, 'desktop_file_id_en');
    }

    public function desktopFileAr(): BelongsTo
    {
        return $this->belongsTo(File::class, 'desktop_file_id_ar');
    }

    public function mobileFileEn(): BelongsTo
    {
        return $this->belongsTo(File::class, 'mobile_file_id_en');
    }

    public function mobileFileAr(): BelongsTo
    {
        return $this->belongsTo(File::class, 'mobile_file_id_ar');
    }

    protected function fileUrl(?File $file): ?string
    {
        return $file ? \Illuminate\Support\Facades\Storage::disk($file->storage_type)->url($file->path) : null;
    }

    public function getDesktopUrlAttribute(): ?string
    {
        return $this->getDesktopUrlEnAttribute();
    }

    public function getMobileUrlAttribute(): ?string
    {
        return $this->getMobileUrlEnAttribute();
    }

    public function getDesktopUrlEnAttribute(): ?string
    {
        return $this->fileUrl($this->desktopFileEn);
    }

    public function getDesktopUrlArAttribute(): ?string
    {
        return $this->fileUrl($this->desktopFileAr);
    }

    public function getMobileUrlEnAttribute(): ?string
    {
        return $this->fileUrl($this->mobileFileEn);
    }

    public function getMobileUrlArAttribute(): ?string
    {
        return $this->fileUrl($this->mobileFileAr);
    }
}
