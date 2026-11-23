<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS12 #24: snapshot, per order item, of the vendor product link / QR the customer arrived through and the
 * platform-commission discount it earned. Written once at checkout; existing orders keep NULL / 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('source_link_channel', 10)->nullable()->after('platform_commission_after_discount')->comment('link | qr');
            $table->timestamp('source_link_clicked_at')->nullable()->after('source_link_channel');
            $table->foreignUuid('source_link_rule_id')->nullable()->after('source_link_clicked_at')
                ->constrained('product_link_commission_rules')->nullOnDelete();
            $table->json('source_link_rule_snapshot')->nullable()->after('source_link_rule_id');
            $table->decimal('source_link_discount', 19, 4)->default(0)->after('source_link_rule_snapshot')
                ->comment('Platform commission waived because of the vendor product link');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_link_rule_id');
            $table->dropColumn(['source_link_channel', 'source_link_clicked_at', 'source_link_rule_snapshot', 'source_link_discount']);
        });
    }
};
