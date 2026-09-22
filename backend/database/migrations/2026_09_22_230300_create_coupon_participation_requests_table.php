<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor's/marketer's request to participate in a
 * CouponParticipationInvitation (client feature request #3.2).
 *
 * Payment integration is NOT implemented here (needs a further check
 * against the platform's billing/wallet system per the plan) — requests
 * are created with status='pending' and moved to 'paid' only via a manual
 * admin "mark as paid" action for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_participation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('invitation_id')->constrained('coupon_participation_invitations')->cascadeOnDelete();
            $table->enum('participant_type', ['vendor', 'marketer']);
            $table->uuid('participant_id');
            $table->unsignedBigInteger('offered_fee_amount');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['participant_type', 'participant_id'], 'cpr_participant_index');
            $table->unique(['invitation_id', 'participant_type', 'participant_id'], 'cpr_invitation_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_participation_requests');
    }
};
