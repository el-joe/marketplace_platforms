<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->foreignUuid('marketer_id')->nullable()->after('vendor_id')->constrained('marketers')->nullOnDelete();
        });

        // MySQL requires DROP + RECREATE to add a value to an ENUM column.
        DB::statement("
            ALTER TABLE sub_orders
            MODIFY COLUMN seller_type ENUM('vendor','platform','marketer') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE sub_orders
            MODIFY COLUMN seller_type ENUM('vendor','platform') NOT NULL
        ");

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketer_id');
        });
    }
};
