<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Non-blocking on InnoDB — does not lock reads/writes during execution.
        DB::statement(
            'ALTER TABLE `products`
             ADD FULLTEXT INDEX `products_fulltext_search`
             (`name_en`, `name_ar`, `short_desc_en`, `model_number`)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `products` DROP INDEX `products_fulltext_search`');
    }
};
