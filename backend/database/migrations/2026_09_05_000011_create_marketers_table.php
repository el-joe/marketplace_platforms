<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_verified_at')->nullable()->comment('timestamp when phone was verified');

            // Marketer type
            $table->enum('marketer_type', ['influencer', 'affiliate'])->comment('influencer = paid flat fee + commission; affiliate = commission only');
            $table->string('whatsapp_for_campaigns', 30)->nullable()->comment('WhatsApp number for campaign invitation messages');

            // Status & approval
            $table->enum('global_status', [
                'pending', 'active', 'inactive', 'suspended', 'rejected', 'blacklisted', 'under_review'
            ])->default('pending');
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            // Stats (denormalized counters, updated by service layer)
            $table->unsignedInteger('total_campaigns')->default(0);
            $table->unsignedInteger('total_conversions')->default(0);
            $table->bigInteger('total_earnings')->default(0)->comment('BIGINT base-currency. No /100.');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketers');
    }
};
