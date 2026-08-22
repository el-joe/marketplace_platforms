<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $blockIds = DB::table('page_blocks')->where('block_type', 'sponsored_products')->pluck('id');

        // paid_banner_bookings.page_block_id is a restrict-on-delete FK, so its
        // rows must go before the page_blocks rows they reference.
        DB::table('paid_banner_bookings')->whereIn('page_block_id', $blockIds)->delete();
        DB::table('page_blocks')->whereIn('id', $blockIds)->delete();
        DB::table('block_types')->where('code', 'sponsored_products')->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: the block type and any pages using it
        // have been permanently removed from the product.
    }
};
