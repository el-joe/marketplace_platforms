<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fbn_inbound_requests', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('quantity_received')
                ->comment('Timestamp when the shipment was physically received at the warehouse');
        });
    }

    public function down(): void
    {
        Schema::table('fbn_inbound_requests', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
