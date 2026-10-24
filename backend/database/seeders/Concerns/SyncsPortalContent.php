<?php

namespace Database\Seeders\Concerns;

use App\Models\PortalContent;
use App\Services\PortalContentCountrySync;
use Illuminate\Support\Facades\Schema;

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
 *
 * Works on the global ('*') layer, then fans the result out to every active
 * country's rows (PortalContentCountrySync::refreshFromGlobal): missing country
 * rows are created and non-admin-edited ones refreshed. Before the
 * country_code migration has run only the (legacy) single layer exists.
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
        $perCountry = Schema::hasColumn('portal_contents', 'country_code');

        foreach ($rows as [$pageKey, $blockKey, $fieldKey, $type, $valueEn, $valueAr, $valueUrl, $sortOrder]) {
            $row = PortalContent::firstOrNew(
                ['page_key' => $pageKey, 'block_key' => $blockKey, 'field_key' => $fieldKey]
                + ($perCountry ? ['country_code' => '*'] : [])
            );

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

        if ($perCountry && $touchedPages) {
            app(PortalContentCountrySync::class)->refreshFromGlobal(array_keys($touchedPages));
        }

        foreach (array_keys($touchedPages) as $pageKey) {
            PortalContent::flush($pageKey);
        }

        return $stats;
    }
}
