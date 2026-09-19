<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_special_requests', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'csr_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_special_requests', function (Blueprint $table) {
            $table->dropIndex('csr_customer_status_idx');
        });
    }
};
