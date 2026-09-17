<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * enhancement.md P-17 task 6: data hygiene for product_images.
 *
 * Lists:
 *  - orphan rows: both product_id AND product_variant_id are NULL.
 *  - integrity-broken rows: product_variant_id points at a variant that
 *    belongs to a DIFFERENT product than the row's own product_id.
 *  - missing files: the row's disk/path doesn't exist in storage.
 *
 * --fix deletes only the orphan rows (never the integrity-broken or
 * missing-file rows — those need a human to pick the correct product_id,
 * not an automated delete). Requires --force to skip the confirmation
 * prompt, for non-interactive/CI use.
 *
 * Operators: run this (optionally with --fix) BEFORE the migration that
 * adds the product_images.product_variant_id -> product_variants.id FK
 * in production, so the FK add doesn't fail against dirty rows.
 */
class AuditImages extends Command
{
    protected $signature = 'images:audit {--fix : Delete orphan rows (both FKs null)} {--force : Skip the confirmation prompt when fixing}';

    protected $description = 'Report (and optionally fix) data hygiene issues in product_images';

    public function handle(): int
    {
        $orphans = DB::table('product_images')
            ->whereNull('product_id')
            ->whereNull('product_variant_id')
            ->get(['id', 'path']);

        $mismatched = DB::table('product_images as pi')
            ->join('product_variants as pv', 'pv.id', '=', 'pi.product_variant_id')
            ->whereNotNull('pi.product_id')
            ->whereColumn('pv.product_id', '!=', 'pi.product_id')
            ->get(['pi.id', 'pi.path', 'pi.product_id', 'pv.product_id as variant_product_id']);

        $missingFiles = [];
        DB::table('product_images')->orderBy('id')->chunk(500, function ($rows) use (&$missingFiles) {
            foreach ($rows as $row) {
                $disk = $row->disk ?: 'public';
                try {
                    if (!Storage::disk($disk)->exists($row->path)) {
                        $missingFiles[] = $row;
                    }
                } catch (\Throwable) {
                    $missingFiles[] = $row;
                }
            }
        });

        $this->info('Orphan rows (both FKs null): ' . $orphans->count());
        foreach ($orphans as $row) {
            $this->line("  - {$row->id}  {$row->path}");
        }

        $this->info('Integrity-broken rows (variant belongs to a different product): ' . $mismatched->count());
        foreach ($mismatched as $row) {
            $this->line("  - {$row->id}  path={$row->path}  product_id={$row->product_id} but variant's product_id={$row->variant_product_id}");
        }

        $this->info('Missing files on disk: ' . count($missingFiles));
        foreach ($missingFiles as $row) {
            $this->line("  - {$row->id}  disk={$row->disk}  path={$row->path}");
        }

        if ($this->option('fix')) {
            if ($orphans->isEmpty()) {
                $this->info('No orphan rows to delete.');

                return self::SUCCESS;
            }

            if (!$this->option('force') && !$this->confirm("Delete {$orphans->count()} orphan row(s)?")) {
                $this->warn('Aborted — no rows deleted.');

                return self::SUCCESS;
            }

            DB::table('product_images')->whereIn('id', $orphans->pluck('id'))->delete();
            $this->info("Deleted {$orphans->count()} orphan row(s).");
        }

        return self::SUCCESS;
    }
}
