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
         * Table: commissions Commission rate rules. ColumnTypeDescriptionidUUID PKseller_idUUID FK → seller_profiles.id NULLNULL = applies to all sellerscategory_idUUID FK → categories.id NULLNULL = applies to all categoriesrate_pctDECIMAL(5,2)E.g., 12.50rate_typeENUM('flat','tiered')min_commission_centsBIGINT DEFAULT 0Floormax_commission_centsBIGINT NULLCapeffective_fromDATEeffective_untilDATE NULLNULL = indefinitepriorityINT DEFAULT 0Higher = more specific winscreated_at, updated_atTIMESTAMPS
         */
        Schema::create('commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->nullable()->index();
            $table->uuid('category_id')->nullable()->index();
            $table->decimal('rate_pct', 5, 2);
            $table->enum('rate_type', ['flat', 'tiered']);
            $table->bigInteger('min_commission')->default(0);
            $table->bigInteger('max_commission')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
