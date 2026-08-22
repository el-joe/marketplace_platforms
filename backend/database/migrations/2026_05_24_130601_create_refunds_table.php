<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: refunds ColumnTypeDescriptionidUUID PKorder_idUUID FK → orders.idsub_order_idUUID FK → sub_orders.id NULLIf partial seller-leveloriginal_transaction_idUUID FK → payment_transactions.idrefund_transaction_idUUID FK → payment_transactions.id NULLThe actual refundamountBIGINTcurrencyCHAR(3)reasonENUM('customer_request','out_of_stock','damaged','wrong_item','not_as_described','late_delivery','duplicate_order','other')reason_notesTEXT NULLrefund_typeENUM('full','partial','shipping_only')initiated_by_user_idUUID FK → users.idapproved_by_admin_idUUID FK → users.id NULLseller_charged_backBOOLEAN DEFAULT falseVendor at fault, deduct from payoutstatusENUM('pending','approved','processing','completed','failed','rejected')created_at, updated_atTIMESTAMPS
         */
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('sub_order_id')->nullable()->index();
            $table->uuid('original_transaction_id')->index();
            $table->uuid('refund_transaction_id')->nullable()->index();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->enum('reason', ['customer_request', 'out_of_stock', 'damaged', 'wrong_item', 'not_as_described', 'late_delivery', 'duplicate_order', 'other']);
            $table->text('reason_notes')->nullable();
            $table->enum('refund_type', ['full', 'partial', 'shipping_only']);
            $table->uuid('initiated_by_customer_id')->index();
            $table->uuid('approved_by_admin_id')->nullable()->index();
            $table->boolean('vendor_charged_back')->default(false);
            $table->enum('status', ['pending', 'approved', 'processing', 'completed', 'failed', 'rejected']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
