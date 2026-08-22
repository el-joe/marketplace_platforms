<?php

namespace App\Models;

use App\Enums\SettingCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasUuids;

    // Settings table has only updated_at, no created_at
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'key',
        'value',
        'category',
        'description',
        'is_encrypted',
        'is_public',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => SettingCategory::class,
            'value' => 'json',
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    // ─── Static helpers ───────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('setting:' . $key, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $adminId = null): void
    {
        static::where('key', $key)->update([
            'value' => $value,
            'updated_by_admin_id' => $adminId,
            'updated_at' => now(),
        ]);
        Cache::forget('setting:' . $key);
    }

    public static function getGroup(string $category): Collection
    {
        return Cache::remember('settings:' . $category, 3600, function () use ($category) {
            return static::where('category', $category)->orderBy('key')->get();
        });
    }

    public static function flush(): void
    {
        Cache::flush();
    }

    // ─── Encryption helpers ───────────────────────────────────────────────────

    public function getDisplayValue(): mixed
    {
        if ($this->is_encrypted && is_string($this->value) && $this->value !== '') {
            return '●●●●●●●●';
        }
        return $this->value;
    }

    public function getRawValue(): mixed
    {
        if ($this->is_encrypted && is_string($this->value) && $this->value !== '') {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception) {
                return $this->value;
            }
        }
        return $this->value;
    }

    // ─── Backward-compat helpers ──────────────────────────────────────────────

    public function getTypedValue(): mixed
    {
        if ($this->is_encrypted) {
            return null;
        }
        return $this->value;
    }

    public function getValueType(): string
    {
        $v = $this->value;
        if (is_bool($v))
            return 'bool';
        if (is_int($v) || is_float($v))
            return 'number';
        if (is_array($v))
            return 'array';
        return 'string';
    }
}
