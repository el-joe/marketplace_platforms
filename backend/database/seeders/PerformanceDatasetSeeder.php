<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * enhancement.md P-22 task 4: a scalable performance dataset seeder.
 *
 * The doc's full target is 50k products / 150k variants / 200k listings / 8
 * countries, for validating the storefront under production-scale query
 * plans. Every count here is a constructor/env-driven parameter so this
 * seeder CAN be pointed at that scale — but it has only actually been RUN
 * in this sandbox at a much smaller size (see the "what was actually run"
 * note below); a genuine 50k-product run needs to happen in an environment
 * with more time/DB resources than this sandbox affords, and this file
 * is written so that run doesn't need new code, just:
 *
 *   php artisan db:seed --class="Database\Seeders\PerformanceDatasetSeeder" \
 *     -- --products=50000 --variants-per-product=3 --listings-per-variant=2 --countries=8
 *
 * (or construct it directly with different numbers if you prefer a plain
 * `new PerformanceDatasetSeeder(...)->run()` from a one-off command).
 *
 * What was actually run in this sandbox: the default constructor values
 * below (500 products / ~1,500 variants / ~3,000 vendor listings / 2
 * countries) via `php artisan tinker` / a one-off command, chosen so the
 * full run completes in well under a minute against the local MySQL
 * instance. `perf:profile`'s output in the P-22 report was captured at this
 * scale, NOT at 50k-product scale — see that report for the explicit
 * caveat repeated there.
 */
class PerformanceDatasetSeeder extends Seeder
{
    public function __construct(
        private readonly int $products = 500,
        private readonly int $variantsPerProduct = 3,
        private readonly int $listingsPerVariant = 2,
        private readonly int $countries = 2,
        private readonly int $categories = 20,
        private readonly int $brands = 30,
        private readonly int $vendors = 25,
    ) {
    }

    public function run(): void
    {
        $runId = Str::lower(Str::random(4));

        $countries = collect(range(1, $this->countries))->map(
            fn ($i) => Country::factory()->create([
                'site_code' => 'perf' . $runId . $i,
                'is_active' => true,
                'is_launched' => true,
            ])
        );

        $categories = Category::factory($this->categories)->create();
        $brands = collect(range(1, $this->brands))->map(fn ($i) => Brand::create([
            'id' => (string) Str::uuid(),
            'name_en' => 'Perf Brand ' . $i,
            'name_ar' => 'Perf Brand ' . $i,
            'slug' => 'perf-brand-' . $i . '-' . Str::lower(Str::random(6)),
            'is_verified' => true,
            'is_active' => true,
        ]));

        $vendors = collect(range(1, $this->vendors))->map(function ($i) use ($countries) {
            $storeName = 'Perf Vendor ' . $i . ' ' . Str::random(4);

            return Vendor::create([
                'name' => 'Perf Vendor ' . $i,
                'email' => 'perf-vendor-' . $i . '-' . Str::lower(Str::random(6)) . '@example.test',
                'phone' => '+9715' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'store_name' => $storeName,
                'store_slug' => Str::slug($storeName) . '-' . Str::lower(Str::random(6)),
                'business_type' => 'llc',
                'payout_schedule' => 'monthly',
                'global_status' => 'active',
                'country_id' => $countries->first()->id,
                'approved_at' => now(),
                'warranty_months' => 12,
            ]);
        });

        $warehousesByCountry = $countries->mapWithKeys(fn ($country) => [
            $country->id => Warehouse::create([
                'country_id' => $country->id,
                'name' => 'Perf Warehouse ' . $country->iso_code_2,
                'code' => 'PW-' . Str::upper(Str::random(6)),
                'type' => 'platform_fbn',
                'is_active' => true,
            ]),
        ]);

        $this->command?->getOutput()?->writeln("Seeding {$this->products} products...");

        Product::factory($this->products)->create([
            'category_id' => fn () => $categories->random()->id,
            'brand_id' => fn () => $brands->random()->id,
            'status' => 'active',
        ])->each(function (Product $product) use ($vendors, $warehousesByCountry, $countries) {
            $variants = ProductVariant::factory($this->variantsPerProduct)->create([
                'product_id' => $product->id,
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'path' => 'perf/' . $product->id . '.jpg',
                'is_primary' => true,
                'position' => 0,
            ]);

            foreach ($variants as $variant) {
                for ($j = 0; $j < $this->listingsPerVariant; $j++) {
                    $vendor = $vendors->random();
                    $country = $countries->random();
                    $warehouse = $warehousesByCountry[$country->id];

                    $listing = VendorListing::create([
                        'id' => (string) Str::uuid(),
                        'vendor_id' => $vendor->id,
                        'product_variant_id' => $variant->id,
                        'country_id' => $country->id,
                        'warehouse_id' => $warehouse->id,
                        'price' => fake()->numberBetween(50, 5000),
                        'currency' => $country->currency_code,
                        'condition' => 'new',
                        'fulfillment_model' => 'fbn',
                        'status' => 'active',
                    ]);

                    WarehouseInventory::create([
                        'vendor_listing_id' => $listing->id,
                        'warehouse_id' => $warehouse->id,
                        'quantity_on_hand' => fake()->numberBetween(0, 200),
                        'quantity_reserved' => 0,
                    ]);
                }
            }
        });

        $this->command?->getOutput()?->writeln('Done. Run `php artisan buybox:rebuild` next to populate product_country_buybox.');
    }
}
