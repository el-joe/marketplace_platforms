<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_contract_acceptances', function (Blueprint $table) {
            $table->index(['customer_id', 'marketer_contract_version_id', 'order_id'], 'mca_customer_version_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_contract_acceptances', function (Blueprint $table) {
            $table->dropIndex('mca_customer_version_order_index');
        });
    }
};
