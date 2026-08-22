<?php

namespace App\Models;

use App\Enums\PortalContentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Generic, admin-editable bilingual content block used to power static
 * marketing text and links on the public portal (resources/views/portal/*).
 *
 * Rows are grouped by page_key/block_key/field_key, e.g.:
 *   page_key='faq', block_key='faq_item_1', field_key='question', type='text'
 *   page_key='home', block_key='hero', field_key='cta_button', type='link'
 *
 * See app/Helpers/portal_content.php for the blade-facing helpers
 * (portal_content() / portal_link()) that read from this model.
 */
class PortalContent extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_key',
        'block_key',
        'field_key',
        'type',
        'value_en',
        'value_ar',
        'value_url',
        'sort_order',
        'is_active',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'type' => PortalContentType::class,
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    // ─── Static helpers ───────────────────────────────────────────────────────

    /**
     * All rows for a page, cached and keyed by "{block_key}.{field_key}".
     *
     * NOTE: the cache payload is stored as plain scalar arrays (not
     * serialized Eloquent objects) and rehydrated into models on read.
     * The 'database' cache driver in this environment corrupts serialized
     * PHP object graphs on roundtrip (confirmed to affect Setting::getGroup()
     * identically), so caching arrays here avoids that failure mode.
     */
    public static function forPage(string $pageKey): Collection
    {
        $rows = Cache::remember('portal_content:' . $pageKey, 3600, function () use ($pageKey) {
            return static::where('page_key', $pageKey)
                ->orderBy('block_key')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (PortalContent $row) => $row->attributesToArray())
                ->values()
                ->all();
        });

        return collect($rows)
            ->map(function (array $attributes) {
                $model = new static();
                $model->setRawAttributes($attributes, true);
                $model->exists = true;

                return $model;
            })
            ->keyBy(fn (PortalContent $row) => $row->block_key . '.' . $row->field_key);
    }

    /**
     * Clear the cached rows for a page (call after saving edits in admin).
     */
    public static function flush(string $pageKey): void
    {
        Cache::forget('portal_content:' . $pageKey);
    }
}
