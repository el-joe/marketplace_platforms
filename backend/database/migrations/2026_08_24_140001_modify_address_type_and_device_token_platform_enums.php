<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE addresses MODIFY address_type ENUM('home','work','shipping','billing','both') NOT NULL");
        DB::statement("ALTER TABLE device_tokens MODIFY platform ENUM('ios','android','web') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE addresses MODIFY address_type ENUM('shipping','billing','both') NOT NULL");
        DB::statement("ALTER TABLE device_tokens MODIFY platform ENUM('ios','android') NOT NULL");
    }
};
