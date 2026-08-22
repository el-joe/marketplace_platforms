<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->char('shipping_company_id', 36)
                ->nullable()
                ->after('agent_type')
                ->comment('NULL = platform-employed agent');
            $table->foreign('shipping_company_id')->references('id')->on('shipping_companies')->nullOnDelete();

            $table->char('added_by_supervisor_id', 36)->nullable()->after('shipping_company_id');
            $table->foreign('added_by_supervisor_id')->references('id')->on('shipping_company_supervisors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropForeign(['shipping_company_id']);
            $table->dropForeign(['added_by_supervisor_id']);
            $table->dropColumn(['shipping_company_id', 'added_by_supervisor_id']);
        });
    }
};
