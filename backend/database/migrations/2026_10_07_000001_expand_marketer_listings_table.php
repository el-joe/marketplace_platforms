<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            $table->foreignUuid('warehouse_id')->nullable()->after('country_id')->constrained('warehouses')->nullOnDelete();
            $table->enum('fulfillment_model', ['fbn'])->nullable()->default('fbn')->after('warehouse_id');
            $table->text('rejection_reason')->nullable()->after('paused_reason');
            $table->foreignUuid('approved_by_admin_id')->nullable()->after('rejection_reason')->constrained('admin_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_admin_id');
            $table->unsignedInteger('low_stock_threshold')->nullable()->default(0)->after('approved_at');
            $table->unsignedInteger('declared_weight_grams')->nullable()->after('low_stock_threshold');
            $table->decimal('declared_length_cm', 8, 2)->nullable()->after('declared_weight_grams');
            $table->decimal('declared_width_cm', 8, 2)->nullable()->after('declared_length_cm');
            $table->decimal('declared_height_cm', 8, 2)->nullable()->after('declared_width_cm');
            $table->string('handling_class', 50)->nullable()->after('declared_height_cm');
            $table->text('condition_notes')->nullable()->after('handling_class');
            $table->string('vendor_sku', 100)->nullable()->after('condition_notes');
        });

        // MySQL requires DROP + RECREATE to expand an ENUM column.
        DB::statement("
            ALTER TABLE marketer_listings
            MODIFY COLUMN status ENUM('draft','pending_review','active','paused','rejected','out_of_stock','archived')
            NOT NULL DEFAULT 'draft'
        ");
    }

    public function down(): void
    {
        // Shrink enum back before dropping columns (avoids data-mismatch errors).
        DB::statement("
            ALTER TABLE marketer_listings
            MODIFY COLUMN status ENUM('active','paused','archived')
            NOT NULL DEFAULT 'active'
        ");

        Schema::table('marketer_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('fulfillment_model');
            $table->dropColumn('rejection_reason');
            $table->dropConstrainedForeignId('approved_by_admin_id');
            $table->dropColumn('approved_at');
            $table->dropColumn('low_stock_threshold');
            $table->dropColumn('declared_weight_grams');
            $table->dropColumn('declared_length_cm');
            $table->dropColumn('declared_width_cm');
            $table->dropColumn('declared_height_cm');
            $table->dropColumn('handling_class');
            $table->dropColumn('condition_notes');
            $table->dropColumn('vendor_sku');
        });
    }
};
