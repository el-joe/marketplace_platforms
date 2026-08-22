<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_campaign_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_campaign_offer_id')
                ->constrained('vendor_campaign_offers')->cascadeOnDelete();
            $table->uuid('marketer_id');
            $table->enum('status', [
                'pending',
                'accepted',
                'declined',
                'expired',
                'revoked',
            ])->default('pending');
            $table->text('marketer_note')->nullable()
                ->comment('Optional note from marketer when accepting or declining');
            $table->text('vendor_note')->nullable()
                ->comment('Private note from vendor visible only to vendor and admin');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable()
                ->comment('Copied from offer.invitation_deadline at invite time — individual override possible');
            $table->uuid('resulting_campaign_id')->nullable()
                ->comment('The marketer_campaigns row auto-created when this invitation is accepted');
            $table->timestamps();

            $table->unique(['vendor_campaign_offer_id', 'marketer_id'],'v_c_invitations_unique');
            $table->index(['marketer_id', 'status'],'m_c_invitations_status_index');
            $table->index(['vendor_campaign_offer_id', 'status'],'v_c_invitations_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_campaign_invitations');
    }
};
