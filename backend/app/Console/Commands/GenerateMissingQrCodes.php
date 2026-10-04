<?php

namespace App\Console\Commands;

use App\Models\MarketerProfile;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\BrandedQrService;
use Illuminate\Console\Command;

class GenerateMissingQrCodes extends Command
{
    protected $signature = 'qr:generate-missing
                            {--type=all : Which entities to process: products, vendors, marketers, or all}
                            {--force : Regenerate even if a QR already exists}
                            {--dry-run : List what would be generated without writing anything}';

    protected $description = 'Generate branded QR codes for products, vendors, and marketer profiles that are missing one';

    public function handle(BrandedQrService $qrService): int
    {
        $type = $this->option('type');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run mode — no files will be written.');
        }

        if (in_array($type, ['all', 'products'])) {
            $this->processProducts($qrService, $force, $dryRun);
        }

        if (in_array($type, ['all', 'vendors'])) {
            $this->processVendors($qrService, $force, $dryRun);
        }

        if (in_array($type, ['all', 'marketers'])) {
            $this->processMarketers($qrService, $force, $dryRun);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function processProducts(BrandedQrService $qrService, bool $force, bool $dryRun): void
    {
        $query = Product::whereNotNull('slug');

        if (! $force) {
            $query->whereNull('qr_code_path');
        }

        $total = $query->count();
        $this->info("Products to process: {$total}");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($products) use ($qrService, $dryRun, $bar) {
            foreach ($products as $product) {
                $scanUrl = route('qr.scan.product', ['slug' => $product->slug]);
                $label = $product->name_ar ?? $product->name_en ?? $product->slug;
                $storagePath = "qr/products/{$product->slug}.png";

                if (! $dryRun) {
                    $path = $qrService->generateAndStore($scanUrl, $label, $storagePath);
                    $product->updateQuietly(['qr_code_path' => $path]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function processVendors(BrandedQrService $qrService, bool $force, bool $dryRun): void
    {
        $query = Vendor::whereNotNull('store_slug');

        if (! $force) {
            $query->whereNull('qr_code_path');
        }

        $total = $query->count();
        $this->info("Vendors to process: {$total}");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($vendors) use ($qrService, $dryRun, $bar) {
            foreach ($vendors as $vendor) {
                $scanUrl = route('qr.scan.vendor', ['slug' => $vendor->store_slug]);
                $label = $vendor->store_name ?? $vendor->name;
                $storagePath = "qr/vendors/{$vendor->store_slug}.png";

                if (! $dryRun) {
                    $path = $qrService->generateAndStore($scanUrl, $label, $storagePath);
                    $vendor->updateQuietly(['qr_code_path' => $path]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function processMarketers(BrandedQrService $qrService, bool $force, bool $dryRun): void
    {
        $query = MarketerProfile::whereNotNull('profile_slug');

        if (! $force) {
            $query->whereNull('qr_code_path');
        }

        $total = $query->count();
        $this->info("Marketer profiles to process: {$total}");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->with('marketer:id,name')->chunkById(100, function ($profiles) use ($qrService, $dryRun, $bar) {
            foreach ($profiles as $profile) {
                $scanUrl = route('qr.scan.marketer', ['slug' => $profile->profile_slug]);
                $label = $profile->marketer?->name ?? $profile->profile_slug;
                $storagePath = "qr/marketers/{$profile->profile_slug}.png";

                if (! $dryRun) {
                    $path = $qrService->generateAndStore($scanUrl, $label, $storagePath);
                    $profile->updateQuietly(['qr_code_path' => $path]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
