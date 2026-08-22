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
         * Table: orders The header record — one per checkout session, even if items are from multiple sellers. ColumnTypeDescriptionidUUID PKorder_numberVARCHAR(20) UNIQUENOON-2026-001234 — human-friendlycustomer_idUUID FK → users.idstatusENUM('placed','confirmed','partially_shipped','shipped','partially_delivered','delivered','completed','cancelled','refunded','disputed')Aggregate status across sub-orderscurrencyCHAR(3)subtotalBIGINTSum of order_itemsdiscountBIGINT DEFAULT 0Coupons + promotionsshippingBIGINTTotal shipping feestaxBIGINTVAT totalcod_feeBIGINT DEFAULT 0If COD paymenttotalBIGINTWhat customer payscoupon_idUUID FK → coupons.id NULLcoupon_code_usedVARCHAR(50) NULLSnapshot — coupon may be deleted laterpayment_methodENUM('card','wallet','cod','bnpl','bank_transfer')payment_statusENUM('pending','authorized','captured','failed','refunded','partially_refunded')shipping_address_snapshotJSONBFull address copy at order timebilling_address_snapshotJSONB NULLcustomer_notesTEXT NULL"Leave at door"ip_addressVARCHAR(45)For fraud analysisuser_agentTEXT NULLdevice_fingerprintVARCHAR(255) NULLrisk_scoreDECIMAL(5,2) NULL0–100, set by fraud jobplaced_atTIMESTAMPcompleted_atTIMESTAMP NULLWhen all sub-orders deliveredcancelled_atTIMESTAMP NULLcreated_at, updated_at, deleted_atTIMESTAMPS
         */
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 20)->unique();
            $table->uuid('customer_id')->index();
            $table->enum('status', ['placed', 'confirmed', 'partially_shipped', 'shipped', 'partially_delivered', 'delivered', 'completed', 'cancelled', 'refunded', 'disputed']);
            $table->char('currency', 3);
            $table->bigInteger('subtotal');
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('shipping');
            $table->bigInteger('tax');
            $table->bigInteger('cod_fee')->default(0);
            $table->bigInteger('total');
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code_used', 50)->nullable();
            $table->enum('payment_method', ['card', 'wallet', 'cod', 'bnpl', 'bank_transfer']);
            $table->enum('payment_status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded']);
            $table->json('shipping_address_snapshot');
            $table->json('billing_address_snapshot')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
