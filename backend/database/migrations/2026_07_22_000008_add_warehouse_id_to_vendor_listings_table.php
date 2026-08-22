<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->foreignUuid('warehouse_id')->nullable()->after('id')
                ->constrained('warehouses')->nullOnDelete()
                ->comment('Which warehouse this listing ships from, used for exceptional zone / gap resolution.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
