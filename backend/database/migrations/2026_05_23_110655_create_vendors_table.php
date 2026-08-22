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
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');


            $table->string('store_name')->unique();
            $table->string('store_slug')->unique();
            $table->text('store_description')->nullable();
            $table->string('business_name')->nullable();
            $table->enum('business_type', ['individual', 'sole_prop', 'llc', 'corp'])->default('individual');
            $table->string('business_registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->uuid('business_address_id')->nullable()->index();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->uuid('default_warehouse_id')->nullable()->index();
            $table->enum('payout_schedule', ['weekly', 'biweekly', 'monthly'])->default('monthly');

            $table->boolean('payout_hold_active')->default(false);
            $table->text('payout_hold_reason')->nullable();
            $table->decimal('store_rating_avg', 3, 2)->default(0.00);
            $table->integer('store_rating_count')->default(0);

            $table->decimal('total_sales', 10, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->decimal('return_rate_pct', 5, 2)->default(0.00);
            $table->decimal('cancellation_rate_pct', 5, 2)->default(0.00);
            $table->decimal('sla_compliance_pct', 5, 2)->default(0.00);
            $table->decimal('strikes_count', 5, 2)->default(0.00);

            $table->uuid('country_id')->index()->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            $table->boolean('approved_at')->default(false);
            $table->uuid('approved_by_admin_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
