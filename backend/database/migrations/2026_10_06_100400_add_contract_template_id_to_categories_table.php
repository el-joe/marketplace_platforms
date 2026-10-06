<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product categories get a contract template. Guarded: the column already exists on databases
     * where the earlier contract migrations ran.
     */
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'contract_template_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('contract_template_id')->nullable()->after('is_active');
            $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['contract_template_id']);
            $table->dropColumn('contract_template_id');
        });
    }
};
