<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `paid_ad_slots`
            MODIFY COLUMN `max_concurrent`
            INT UNSIGNED NOT NULL DEFAULT 1
            COMMENT 'Bookings that may run on the same day (rotation).'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `paid_ad_slots`
            MODIFY COLUMN `max_concurrent`
            TINYINT UNSIGNED NOT NULL DEFAULT 1
            COMMENT 'Bookings that may run on the same day (rotation).'
        ");
    }
};
