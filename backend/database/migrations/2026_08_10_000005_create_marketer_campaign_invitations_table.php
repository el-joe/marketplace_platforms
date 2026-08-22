<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_campaign_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('marketer_campaigns')->cascadeOnDelete();

            // The marketer (vendor with marketer_type set)
            $table->foreignUuid('marketer_vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->enum('status', [
                'pending',    // invitation sent, awaiting response
                'accepted',   // marketer accepted
                'rejected',   // marketer rejected
                'timed_out',  // no response within timeout window
                'replaced',   // this slot was replaced by another marketer
                'cancelled',  // campaign was cancelled before response
            ])->default('pending');

            // Invitation acceptance window (hours, snapshotted from settings at invite time)
            $table->unsignedSmallInteger('acceptance_window_hours')->default(12);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('marketer_note')->nullable();
            $table->text('decline_reason')->nullable();

            // Referral link and QR for this specific marketer on this campaign
            $table->string('referral_code', 50)->unique()->nullable();
            $table->string('referral_link', 500)->nullable();
            $table->string('qr_code_path', 500)->nullable();

            // WhatsApp notification tracking
            $table->boolean('whatsapp_sent')->default(false);
            $table->timestamp('whatsapp_sent_at')->nullable();

            // Replacement tracking
            // If this invitation was created as a replacement, link to original
            $table->foreignUuid('replaced_invitation_id')->nullable()
                  ->constrained('marketer_campaign_invitations')->nullOnDelete();

            // Commission earned on this invitation (running total)
            $table->bigInteger('total_commission_earned')->default(0)
                  ->comment('BIGINT base-currency. No /100.');
            $table->unsignedInteger('total_conversions')->default(0);

            $table->timestamps();

            $table->index(['campaign_id', 'marketer_vendor_id'], 'mci_campaign_marketer_idx');
            $table->index(['status', 'expires_at'], 'mci_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_invitations');
    }
};
