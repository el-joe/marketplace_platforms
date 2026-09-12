<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `paid_ad_creatives`
            MODIFY COLUMN `subtitle_en` TEXT NULL,
            MODIFY COLUMN `subtitle_ar` TEXT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `paid_ad_creatives`
            MODIFY COLUMN `subtitle_en` VARCHAR(255) NULL,
            MODIFY COLUMN `subtitle_ar` VARCHAR(255) NULL
        ");
    }
};
