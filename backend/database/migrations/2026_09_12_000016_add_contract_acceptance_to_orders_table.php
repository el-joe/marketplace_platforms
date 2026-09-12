<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('marketer_contract_acceptance_id')->nullable()->after('payment_method');

            $table->foreign('marketer_contract_acceptance_id')
                ->references('id')->on('marketer_contract_acceptances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['marketer_contract_acceptance_id']);
            $table->dropColumn('marketer_contract_acceptance_id');
        });
    }
};
