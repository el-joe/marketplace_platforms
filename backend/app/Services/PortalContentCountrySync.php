<?php

namespace App\Services;

use App\Models\Country;
use App\Models\PortalContent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Materialises the global ('*') Portal Content rows as per-country rows.
 *
 * All methods are idempotent and never overwrite an existing country row's
 * values (admin edits are safe); refreshFromGlobal() only refreshes rows no
 * admin has edited.
 */
class PortalContentCountrySync
{
    /** True once the country_code column exists (old data migrations run before it). */
    public function isReady(): bool
    {
        return Schema::hasTable('portal_contents') && Schema::hasColumn('portal_contents', 'country_code');
    }

    /** Insert every missing global row for one country. Returns rows created. */
    public function copyGlobalToCountry(Country|string $country): int
    {
        return $this->copy($this->siteCode($country), null);
    }

    /** Insert the missing global rows of one page for a country. Returns rows created. */
    public function ensureCountryRows(Country|string $country, string $pageKey): int
    {
        return $this->copy($this->siteCode($country), [$pageKey]);
    }

    /** Copy global rows to every country (active or not) that has a site_code. */
    public function copyGlobalToAllCountries(): int
    {
        if (! $this->isReady()) {
            return 0;
        }

        $total = 0;
        foreach (Country::withTrashed()->whereNotNull('site_code')->where('site_code', '!=', '')->pluck('site_code') as $code) {
            $total += $this->copy(strtolower($code), null);
        }

        return $total;
    }

    /**
     * After the global layer changed (seeder/deploy): for every active country
     * create missing rows, refresh non-admin-edited rows from the global row and
     * realign type/sort_order on admin-edited ones.
     *
     * @param  array<int, string>|null  $pageKeys  limit to these pages
     * @return array{created: int, refreshed: int}
     */
    public function refreshFromGlobal(?array $pageKeys = null): array
    {
        $stats = ['created' => 0, 'refreshed' => 0];
        if (! $this->isReady()) {
            return $stats;
        }

        $codes = Country::where('is_active', true)->whereNotNull('site_code')->where('site_code', '!=', '')->pluck('site_code')->map(fn ($c) => strtolower($c));

        foreach ($codes as $code) {
            $stats['created'] += $this->copy($code, $pageKeys);

            $global = $this->globalRows($pageKeys)->get();
            $existing = DB::table('portal_contents')->where('country_code', $code)
                ->when($pageKeys, fn ($q) => $q->whereIn('page_key', $pageKeys))
                ->get()->keyBy(fn ($r) => $r->page_key.'|'.$r->block_key.'|'.$r->field_key);

            foreach ($global as $g) {
                $row = $existing->get($g->page_key.'|'.$g->block_key.'|'.$g->field_key);
                if (! $row) {
                    continue;
                }

                $update = [];
                foreach (['type', 'sort_order'] as $col) {
                    if ($row->$col != $g->$col) {
                        $update[$col] = $g->$col;
                    }
                }
                if ($row->updated_by_admin_id === null) {
                    foreach (['value_en', 'value_ar', 'value_url'] as $col) {
                        if ($row->$col !== $g->$col) {
                            $update[$col] = $g->$col;
                        }
                    }
                }
                if ($update) {
                    DB::table('portal_contents')->where('id', $row->id)->update($update + ['updated_at' => Carbon::now()]);
                    $stats['refreshed']++;
                }
            }
        }

        return $stats;
    }

    private function copy(?string $code, ?array $pageKeys): int
    {
        if (! $code || $code === '*' || ! $this->isReady()) {
            return 0;
        }

        $existing = DB::table('portal_contents')->where('country_code', $code)
            ->when($pageKeys, fn ($q) => $q->whereIn('page_key', $pageKeys))
            ->select('page_key', 'block_key', 'field_key')->get()
            ->mapWithKeys(fn ($r) => [$r->page_key.'|'.$r->block_key.'|'.$r->field_key => true]);

        $now = Carbon::now();
        $created = 0;
        $batch = [];

        foreach ($this->globalRows($pageKeys)->cursor() as $g) {
            if ($existing->has($g->page_key.'|'.$g->block_key.'|'.$g->field_key)) {
                continue;
            }
            $batch[] = [
                'id' => (string) Str::uuid(),
                'country_code' => $code,
                'page_key' => $g->page_key,
                'block_key' => $g->block_key,
                'field_key' => $g->field_key,
                'type' => $g->type,
                'value_en' => $g->value_en,
                'value_ar' => $g->value_ar,
                'value_url' => $g->value_url,
                'sort_order' => $g->sort_order,
                'is_active' => $g->is_active,
                'updated_by_admin_id' => $g->updated_by_admin_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) >= 200) {
                $created += DB::table('portal_contents')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $created += DB::table('portal_contents')->insertOrIgnore($batch);
        }

        if ($created) {
            foreach ($pageKeys ?? DB::table('portal_contents')->where('country_code', $code)->distinct()->pluck('page_key')->all() as $page) {
                PortalContent::flush($page, $code);
            }
        }

        return $created;
    }

    private function globalRows(?array $pageKeys)
    {
        return DB::table('portal_contents')->where('country_code', '*')
            ->when($pageKeys, fn ($q) => $q->whereIn('page_key', $pageKeys));
    }

    private function siteCode(Country|string $country): ?string
    {
        $code = $country instanceof Country ? $country->site_code : $country;

        return $code === null ? null : strtolower($code);
    }
}
