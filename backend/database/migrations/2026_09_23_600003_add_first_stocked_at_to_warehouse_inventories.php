<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->timestamp('first_stocked_at')->nullable()->after('bin_location')
                ->comment('Timestamp when inventory was first received — used for free storage period calculation');
        });

        DB::statement('UPDATE warehouse_inventories SET first_stocked_at = created_at WHERE quantity_on_hand > 0 AND first_stocked_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->dropColumn('first_stocked_at');
        });
    }
};
