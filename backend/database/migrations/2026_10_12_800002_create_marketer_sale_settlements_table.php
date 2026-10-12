<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money snapshot for a sub-order whose SELLER is a marketer (own-FBN listing, or a peer promoter's proxy
 * listing where the owner influencer is the seller). One row per sub-order (unique) = idempotent settlement.
 * All amounts are BIGINT base units. net = vendor_payout of the sub-order, i.e. the same figure vendors are paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_sale_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->foreignUuid('sub_order_id')->unique()->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('sold_via', 20)->default('direct')->comment('direct | peer (sold through another influencer proxy listing)');
            $table->bigInteger('gross_amount')->default(0)->comment('sub_orders.subtotal');
            $table->bigInteger('coupon_cost_amount')->default(0);
            $table->bigInteger('platform_commission_amount')->default(0)->comment('category commission after discount, charged to the seller');
            $table->decimal('commission_vat_rate', 5, 2)->default(0);
            $table->bigInteger('commission_vat_amount')->default(0);
            $table->bigInteger('gateway_fee_amount')->default(0);
            $table->bigInteger('peer_commission_amount')->default(0)->comment('promoter commission paid by the owner (marketer_commission_owner=marketer)');
            $table->bigInteger('other_deduction_amount')->default(0)->comment('residual, e.g. seller shipping contribution');
            $table->bigInteger('net_amount')->default(0)->comment('credited to the marketer wallet');
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['marketer_id', 'credited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_sale_settlements');
    }
};
