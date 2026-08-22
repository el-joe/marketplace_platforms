<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_payment_method_id')->index();
            $table->string('gateway_code', 50);
            $table->string('event_type', 100)->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->tinyInteger('signature_valid')->nullable();
            $table->tinyInteger('processed')->default(0);
            $table->text('processing_error')->nullable();
            $table->uuid('payment_transaction_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('country_payment_method_id')
                ->references('id')->on('country_payment_methods')->onDelete('cascade');

            $table->foreign('payment_transaction_id')
                ->references('id')->on('payment_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_webhook_logs');
    }
};
