<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ContentSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'group',
        'section',
        'key',
        'type',
        'value',
        'options',
        'default_value',
        'allowed_extensions',
        'max_size_kb',
        'is_public',
        'sort_order',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_public' => 'boolean',
            'max_size_kb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('content_setting:' . $key, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $adminId = null): void
    {
        static::where('key', $key)->update([
            'value' => $value,
            'updated_by_admin_id' => $adminId,
        ]);
        Cache::forget('content_setting:' . $key);
    }

    public static function getGroup(string $group): Collection
    {
        return Cache::remember('content_settings:' . $group, 3600, function () use ($group) {
            return static::where('group', $group)->orderBy('sort_order')->get();
        });
    }

    public static function getPublic(): Collection
    {
        return Cache::remember('content_settings:public', 3600, function () {
            return static::where('is_public', true)->orderBy('sort_order')->get();
        });
    }

    public static function flush(): void
    {
        Cache::flush();
    }
}
