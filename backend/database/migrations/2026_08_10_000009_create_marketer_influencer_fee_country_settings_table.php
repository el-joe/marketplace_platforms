<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_influencer_fee_country_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->unique()->constrained('countries')->cascadeOnDelete();

            // Fee per influencer slot selected on a vendor campaign
            $table->bigInteger('fee_per_influencer')->default(0)
                  ->comment('Platform fee per influencer selected. BIGINT base-currency. No /100.');
            $table->char('currency', 3);

            $table->foreignUuid('updated_by_admin_id')->nullable();
            $table->foreign('updated_by_admin_id', 'mkt_inf_fee_upd_admin_fk')->references('id')->on('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_influencer_fee_country_settings');
    }
};
