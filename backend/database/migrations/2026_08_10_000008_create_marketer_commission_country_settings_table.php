<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_commission_country_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained('countries')->cascadeOnDelete();

            // Per marketer type
            $table->bigInteger('influencer_commission_amount')->default(0)
                  ->comment('Commission influencer earns per conversion. BIGINT base-currency. No /100.');
            $table->bigInteger('affiliate_commission_amount')->default(0)
                  ->comment('Commission affiliate earns per conversion. BIGINT base-currency. No /100.');

            $table->char('currency', 3);

            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'country_id'], 'mkt_comm_country_cat_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_commission_country_settings');
    }
};
