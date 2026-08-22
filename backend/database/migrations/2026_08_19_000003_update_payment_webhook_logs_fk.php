<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_webhook_logs', function (Blueprint $table): void {
            // Drop FK to old table
            $table->dropForeign(['country_payment_method_id']);
            $table->dropIndex(['country_payment_method_id']);

            // Rename column and add new FK
            $table->renameColumn('country_payment_method_id', 'country_payment_gateway_id');
        });

        Schema::table('payment_gateway_webhook_logs', function (Blueprint $table): void {
            $table->index('country_payment_gateway_id');
            $table->foreign('country_payment_gateway_id')
                ->references('id')->on('country_payment_gateways')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_webhook_logs', function (Blueprint $table): void {
            $table->dropForeign(['country_payment_gateway_id']);
            $table->dropIndex(['country_payment_gateway_id']);
            $table->renameColumn('country_payment_gateway_id', 'country_payment_method_id');
        });
        Schema::table('payment_gateway_webhook_logs', function (Blueprint $table): void {
            $table->index('country_payment_method_id');
            $table->foreign('country_payment_method_id')
                ->references('id')->on('country_payment_methods')->onDelete('cascade');
        });
    }
};
