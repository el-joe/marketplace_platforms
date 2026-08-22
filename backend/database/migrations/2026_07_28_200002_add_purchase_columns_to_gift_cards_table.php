<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->char('purchased_by_customer_id', 36)
                ->nullable()
                ->after('issued_to_customer_id')
                ->comment('Customer who paid for this card. NULL = admin-generated, not purchased.');

            $table->string('recipient_email')->nullable()->after('purchased_by_customer_id')
                ->comment('Email to deliver code+PIN to. NULL = deliver to buyer.');
            $table->string('recipient_name', 150)->nullable()->after('recipient_email');

            $table->char('purchase_order_id', 36)->nullable()->after('recipient_name')
                ->comment('The orders.id where this gift card was purchased.');

            $table->timestamp('delivery_sent_at')->nullable()->after('purchase_order_id')
                ->comment('When the code+PIN email/SMS was sent to the recipient.');
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn([
                'purchased_by_customer_id', 'recipient_email', 'recipient_name',
                'purchase_order_id', 'delivery_sent_at',
            ]);
        });
    }
};
