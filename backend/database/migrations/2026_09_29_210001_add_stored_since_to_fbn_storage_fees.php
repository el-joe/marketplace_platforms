<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->date('stored_since')->nullable()->after('days_in_storage')
                ->comment('Snapshot of warehouse_inventories.first_stocked_at at fee-generation time — the date the storage clock started');
        });
    }

    public function down(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->dropColumn('stored_since');
        });
    }
};
