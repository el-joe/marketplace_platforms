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
        Schema::create('platform_shipping_subsidies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_zone_id')->constrained('shipping_zones');
            $table->foreignUuid('shipping_method_id')->constrained('shipping_methods');
            $table->char('currency', 3);

            $table->bigInteger('subsidy_cap')->default(0)
                ->comment('Max amount platform covers per delivery, base currency units');

            $table->unsignedInteger('max_subsidy_weight_grams')->nullable()
                ->comment('Max billable weight in grams platform subsidy applies to; NULL = no weight cap');

            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins');
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'shipping_method_id'], 'unique_zone_method_subsidy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_shipping_subsidies');
    }
};
