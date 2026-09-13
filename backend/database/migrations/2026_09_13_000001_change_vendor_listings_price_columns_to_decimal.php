<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `vendor_listings`
            MODIFY `price` DECIMAL(12,2) NOT NULL,
            MODIFY `compare_at_price` DECIMAL(12,2) NULL,
            MODIFY `cost_price` DECIMAL(12,2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `vendor_listings`
            MODIFY `price` BIGINT NOT NULL,
            MODIFY `compare_at_price` BIGINT NULL,
            MODIFY `cost_price` BIGINT NULL');
    }
};
