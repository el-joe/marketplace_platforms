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
         * Table: sub_orders When one order contains items from multiple sellers, each seller's portion becomes a sub-order. Independent fulfillment, tracking, and payouts. ColumnTypeDescriptionidUUID PKorder_idUUID FK → orders.idParentsub_order_numberVARCHAR(25) UNIQUENOON-2026-001234-Aseller_idUUID FK → seller_profiles.idwarehouse_idUUID FK → warehouses.id NULLWhere it ships from (FBN)statusENUM('placed','confirmed','processing','packed','shipped','out_for_delivery','delivered','completed','cancelled','returned','refunded')Use state machine patternfulfillment_modelENUM('fbm','fbn')At time of ordersubtotalBIGINTThis seller's items onlyshippingBIGINTShipping for this sub-ordertaxBIGINTplatform_commissionBIGINTWhat platform earnsseller_payoutBIGINTWhat seller gets (after commission)shipping_method_idUUID FK → shipping_methods.idcarrier_idUUID FK → shipping_carriers.id NULLtracking_numberVARCHAR(100) NULLestimated_delivery_dateDATE NULLshipped_atTIMESTAMP NULLdelivered_atTIMESTAMP NULLcancelled_atTIMESTAMP NULLcancellation_reasonTEXT NULLsla_ship_deadlineTIMESTAMP NULLMust ship by this timesla_breachedBOOLEAN DEFAULT falsecreated_at, updated_atTIMESTAMPS
         */
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->string('sub_order_number', 25)->unique();
            $table->uuid('vendor_id')->index();
            $table->uuid('warehouse_id')->index()->nullable();
            $table->enum('status', ['placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'returned', 'refunded']);
            $table->enum('fulfillment_model', ['fbm', 'fbn']);
            $table->bigInteger('subtotal');
            $table->bigInteger('shipping');
            $table->bigInteger('tax');
            $table->bigInteger('platform_commission');
            $table->bigInteger('vendor_payout');
            $table->uuid('shipping_method_id')->index();
            $table->uuid('carrier_id')->index()->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('sla_ship_deadline')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_orders');
    }
};
