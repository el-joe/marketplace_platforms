<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->text('customer_rejection_reason')
                ->nullable()
                ->comment('Written by customer or delivery agent on customer behalf');
            $table->timestamp('rejected_by_customer_at')->nullable();
            $table->tinyInteger('rejection_reason_mandatory')
                ->default(0)
                ->comment('Set to 1 by system when payment was electronic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'customer_rejection_reason',
                'rejected_by_customer_at',
                'rejection_reason_mandatory',
            ]);
        });
    }
};
