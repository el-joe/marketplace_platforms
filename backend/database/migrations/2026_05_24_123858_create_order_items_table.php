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
         * Table: order_items ColumnTypeDescriptionidUUID PKorder_idUUID FK → orders.idsub_order_idUUID FK → sub_orders.idproduct_variant_idUUID FK → product_variants.idproduct_snapshotJSONBProduct name, image, attributes at purchase timeseller_idUUID FK → seller_profiles.idDenormalizedskuVARCHAR(100)SnapshotquantityINTunit_price_centsBIGINTSnapshot — never live priceunit_cost_price_centsBIGINT NULLSnapshot of vendor's costline_subtotal_centsBIGINTquantity × unit_priceline_discount_centsBIGINT DEFAULT 0Item-level discount allocationline_tax_centsBIGINTline_total_centsBIGINTAfter discount, with taxcommission_rate_pctDECIMAL(5,2)Snapshot of category/seller ratecommission_amount_centsBIGINTComputed at order timefulfillment_statusENUM('pending','picked','packed','shipped','delivered','returned','cancelled')Item-level for partial fulfillmentreturn_eligible_untilDATE NULLReturn window endcreated_at, updated_atTIMESTAMPS
         */
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('sub_order_id')->index();
            $table->uuid('product_variant_id')->index();
            $table->json('product_snapshot');
            $table->uuid('vendor_id')->index();
            $table->string('sku', 100);
            $table->integer('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('unit_cost_price')->nullable();
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_discount')->default(0);
            $table->bigInteger('line_tax');
            $table->bigInteger('line_total');
            $table->decimal('commission_rate_pct', 5, 2);
            $table->bigInteger('commission_amount');
            $table->enum('fulfillment_status', ['pending', 'picked', 'packed', 'shipped', 'delivered', 'returned', 'cancelled']);
            $table->date('return_eligible_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
