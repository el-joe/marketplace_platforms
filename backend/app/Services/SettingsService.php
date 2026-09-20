<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    private const FILES_DISK = 'public';
    private const FILES_PATH = 'settings';

    /**
     * All settings grouped by category, keyed by category value.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Setting>>
     */
    public function allGrouped(): array
    {
        return Setting::orderBy('key')->get()
            ->groupBy(fn(Setting $s) => $s->category instanceof \App\Enums\SettingCategory ? $s->category->value : $s->category)
            ->all();
    }

    /**
     * Public (is_public=1) settings as a flat key => typed value array.
     */
    public function publicMap(): array
    {
        return Setting::where('is_public', true)
            ->get()
            ->mapWithKeys(fn(Setting $s) => [$s->key => $s->getTypedValue()])
            ->all();
    }

    /**
     * Validate and save a single setting by key.
     * Uploaded files are stored under storage/settings and the relative path is saved as the value.
     */
    public function save(string $key, mixed $input, ?Admin $admin = null): void
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        if ($input instanceof UploadedFile) {
            $this->storeFileValue($setting, $input, $admin);
            return;
        }

        if ($setting->is_encrypted) {
            if ($input === '●●●●●●●●' || $input === '') {
                return;
            }
            $value = Crypt::encryptString((string) $input);
        } else {
            $original = $setting->value;
            $value = match (true) {
                is_bool($original) => filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $input,
                is_int($original) => (int) $input,
                is_float($original) => (float) $input,
                is_array($original) => is_string($input) ? (json_decode($input, true) ?? $input) : $input,
                default => (string) ($input ?? ''),
            };
        }

        $setting->update([
            'value' => $value,
            'updated_by_admin_id' => $admin?->id,
            'updated_at' => now(),
        ]);

        Cache::forget('setting:' . $key);
        Cache::forget('settings:' . ($setting->category instanceof \App\Enums\SettingCategory ? $setting->category->value : $setting->category));
    }

    /**
     * Bulk save: array of key => value (or UploadedFile) pairs, plus a separate
     * array of key => UploadedFile for FilePond uploads. Wrapped in a transaction.
     */
    public function saveBulk(array $data, array $files = [], ?Admin $admin = null): void
    {
        DB::transaction(function () use ($data, $files, $admin) {
            foreach ($files as $key => $file) {
                $this->save($key, $file, $admin);
            }

            foreach ($data as $key => $value) {
                if (array_key_exists($key, $files)) {
                    continue;
                }
                $this->save($key, $value, $admin);
            }
        });
    }

    private function storeFileValue(Setting $setting, UploadedFile $file, ?Admin $admin): void
    {
        $oldPath = is_string($setting->value) ? $setting->value : null;

        $path = $file->store(self::FILES_PATH, self::FILES_DISK);

        if ($oldPath && Storage::disk(self::FILES_DISK)->exists($oldPath)) {
            Storage::disk(self::FILES_DISK)->delete($oldPath);
        }

        $setting->update([
            'value' => $path,
            'updated_by_admin_id' => $admin?->id,
            'updated_at' => now(),
        ]);

        Cache::forget('setting:' . $setting->key);
        Cache::forget('settings:' . ($setting->category instanceof \App\Enums\SettingCategory ? $setting->category->value : $setting->category));
    }

    /**
     * Save a group of settings for a category.
     * Encrypted fields: if value === '●●●●●●●●', skip (keep current).
     * Returns count of settings updated.
     */
    public function saveGroup(string $category, array $data, Admin $admin): int
    {
        $count = 0;

        DB::transaction(function () use ($category, $data, $admin, &$count) {
            foreach ($data as $key => $value) {
                $setting = Setting::where('key', $key)
                    ->where('category', $category)
                    ->first();

                if (!$setting) {
                    continue; // don't create unknown keys
                }

                // Encrypted field: if placeholder sent, keep existing value
                if ($setting->is_encrypted) {
                    if ($value === '●●●●●●●●' || $value === '') {
                        continue;
                    }
                    // Encrypt the new plaintext value
                    $value = Crypt::encryptString((string) $value);
                } else {
                    // Coerce to the original PHP type stored in DB
                    $original = $setting->value;

                    $value = match (true) {
                        is_bool($original) => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
                        is_int($original) => (float) $value,
                        is_float($original) => (float) $value,
                        is_array($original) => is_string($value) ? (json_decode($value, true) ?? $value) : $value,
                        default => (string) ($value ?? ''),
                    };
                }

                // Using the JSON cast: assign decoded PHP value, Eloquent auto-encodes
                $setting->update([
                    'value' => $value,
                    'updated_by_admin_id' => $admin->id,
                    'updated_at' => now(),
                ]);

                Cache::forget('setting:' . $key);
                $count++;
            }

            Cache::forget('settings:' . $category);
        });

        Log::info("Settings [{$category}] saved by admin {$admin->id}: {$count} keys updated.");

        return $count;
    }

    /**
     * Return settings as key => display_value array for form pre-fill.
     * Encrypted fields return '●●●●●●●●'.
     */
    public function getGroupForForm(string $category): array
    {
        $settings = Setting::where('category', $category)->get();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getDisplayValue();
        }

        return $result;
    }

    /**
     * Basic validation for a settings array.
     * Returns array of errors keyed by setting key.
     */
    public function validateGroup(string $category, array $data): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            $setting = Setting::where('key', $key)
                ->where('category', $category)
                ->first();

            if (!$setting || $setting->is_encrypted) {
                continue;
            }

            $original = $setting->value;

            if (is_bool($original)) {
                if (!in_array($value, ['0', '1', 0, 1, true, false], true)) {
                    $errors[$key] = 'Must be a boolean (0 or 1).';
                }
            } elseif (is_int($original) || is_float($original)) {
                if (!is_numeric($value)) {
                    $errors[$key] = 'Must be a numeric value.';
                }
            } elseif (str_contains($key, 'email')) {
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = 'Must be a valid email address.';
                }
            } elseif (str_contains($key, 'url') || str_contains($key, 'host')) {
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[$key] = 'Must be a valid URL.';
                }
            } elseif (str_contains($key, 'color')) {
                if ($value !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $errors[$key] = 'Must be a valid hex color (e.g. #0284c7).';
                }
            }
        }

        return $errors;
    }
}
