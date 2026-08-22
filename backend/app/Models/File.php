<?php

namespace App\Models;

// use App\Enums\FileStorageType;
// use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class File extends Model
{

    protected $fillable = [
        'key',
        'path',
        'storage_type',
        'file_type',
        'mime_type',
        'extension',
        'size',
        'model_type',
        'model_id',
        'alt_text_ar',
        'alt_text_en',
        'position',
        'is_primary',
    ];

    protected $appends = ['full_path'];

    protected static function booted(): void
    {
        static::deleting(function (self $file) {
            if ($file->path) {
                Storage::disk($file->disk())->delete($file->path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            // 'storage_type' => FileStorageType::class,
            // 'file_type' => FileType::class,
            'size' => 'integer',
        ];
    }

    public function model()
    {
        return $this->morphTo();
    }

    public function hashes()
    {
        return $this->hasOne(FileHash::class, 'file_id');
    }

    public function disk(): string
    {
        return $this->storage_type instanceof FileStorageType
            ? $this->storage_type->value
            : ($this->storage_type ?: FileStorageType::Public ->value);
    }

    public function getFullPathAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk());

        return url($disk->url($this->path));
    }

    public function getUrlAttribute(): string
    {
        return match ($this->storage_type) {
            's3' => Storage::disk('s3')->url($this->path),
            default => Storage::disk('public')->url($this->path),
        };
    }
}
