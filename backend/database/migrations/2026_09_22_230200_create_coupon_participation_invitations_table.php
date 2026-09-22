<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupon participation invitations (client feature request #3.2): the admin
 * opens an invitation for vendors/marketers to pay a participation fee to
 * join a coupon. `coupon_id` is nullable while the invitation is a draft —
 * the linked coupon is created (or activated) once the invitation is
 * fulfilled/closed by CloseExpiredCouponParticipationInvitations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_participation_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_participants');
            $table->unsignedBigInteger('min_fee_amount');
            $table->string('currency', 3);
            $table->timestamp('registration_deadline');
            $table->enum('status', ['open', 'closed', 'fulfilled', 'cancelled'])->default('open');
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_participation_invitations');
    }
};
