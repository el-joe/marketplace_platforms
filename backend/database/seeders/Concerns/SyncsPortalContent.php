<?php

namespace Database\Seeders\Concerns;

use App\Models\PortalContent;

/**
 * Non-destructive upsert for Portal Content CMS rows.
 *
 * Safe to run on production at any time (it is also invoked by a data
 * migration on deploy):
 *   - a missing row is inserted with the canonical default;
 *   - an existing row that no admin has edited (updated_by_admin_id IS NULL)
 *     is refreshed to the canonical default (type, values, sort order);
 *   - an admin-edited row keeps its values — only `type` and `sort_order`
 *     are realigned so the Blade helper keeps reading it correctly.
 */
trait SyncsPortalContent
{
    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: ?string, 5: ?string, 6: ?string, 7: int}>  $rows
     * @return array{created: int, refreshed: int, preserved: int}
     */
    protected function syncPortalContent(array $rows): array
    {
        $stats = ['created' => 0, 'refreshed' => 0, 'preserved' => 0];
        $touchedPages = [];

        foreach ($rows as [$pageKey, $blockKey, $fieldKey, $type, $valueEn, $valueAr, $valueUrl, $sortOrder]) {
            $row = PortalContent::firstOrNew([
                'page_key' => $pageKey,
                'block_key' => $blockKey,
                'field_key' => $fieldKey,
            ]);

            $structural = ['type' => $type, 'sort_order' => $sortOrder];
            $values = ['value_en' => $valueEn, 'value_ar' => $valueAr, 'value_url' => $valueUrl];

            if (! $row->exists) {
                $row->fill($structural + $values + ['is_active' => true])->save();
                $stats['created']++;
            } elseif ($row->updated_by_admin_id === null) {
                $row->fill($structural + $values)->save();
                $stats['refreshed']++;
            } else {
                $row->fill($structural)->save();
                $stats['preserved']++;
            }

            $touchedPages[$pageKey] = true;
        }

        foreach (array_keys($touchedPages) as $pageKey) {
            PortalContent::flush($pageKey);
        }

        return $stats;
    }
}
