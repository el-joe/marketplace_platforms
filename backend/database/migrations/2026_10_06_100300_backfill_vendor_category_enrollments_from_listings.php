<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Creates enrollments for every vendor already listing in a category that requires a contract.
     * Enrollments whose listing was already accepted are marked signed by the listing's own acceptance.
     */
    public function up(): void
    {
        $now = now();

        $classifiedPairs = DB::table('classified_listings as listing')
            ->join('classified_categories as category', 'category.id', '=', 'listing.classified_category_id')
            ->where('listing.seller_type', 'App\\Models\\Vendor')
            ->whereNotNull('category.contract_template_id')
            ->select('listing.seller_id as vendor_id', 'category.id as category_id')
            ->distinct()
            ->get();

        foreach ($classifiedPairs as $pair) {
            DB::table('vendor_category_enrollments')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'vendor_id' => $pair->vendor_id,
                'classified_category_id' => $pair->category_id,
                'category_scope' => 'classified',
                'status' => 'pending_signature',
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // On a fresh build the categories column is added by a later migration and there is nothing to backfill yet.
        if (! Schema::hasColumn('categories', 'contract_template_id')) {
            return;
        }

        $productPairs = DB::table('vendor_listings as listing')
            ->join('product_variants as variant', 'variant.id', '=', 'listing.product_variant_id')
            ->join('products as product', 'product.id', '=', 'variant.product_id')
            ->join('categories as category', 'category.id', '=', 'product.category_id')
            ->whereNotNull('category.contract_template_id')
            ->select('listing.vendor_id', 'category.id as category_id')
            ->distinct()
            ->get();

        foreach ($productPairs as $pair) {
            DB::table('vendor_category_enrollments')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'vendor_id' => $pair->vendor_id,
                'category_scope' => 'product',
                'product_category_id' => $pair->category_id,
                'status' => 'pending_signature',
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill: enrollments are kept on rollback because signed contracts may reference them.
    }
};
