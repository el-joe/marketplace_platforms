<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_agency_campaign_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_campaign_offer_id')
                ->constrained('travel_agency_campaign_offers', indexName: 'ta_c_invitations_offer_id_foreign')->cascadeOnDelete();
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
                ->comment('Private note from the travel agency visible only to the agency and admin — column name mirrors vendor_campaign_invitations for schema parity');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable()
                ->comment('Copied from offer.invitation_deadline at invite time — individual override possible');
            $table->uuid('resulting_campaign_id')->nullable()
                ->comment('The marketer_campaigns row auto-created when this invitation is accepted');
            $table->timestamps();

            $table->unique(['travel_agency_campaign_offer_id', 'marketer_id'], 'ta_c_invitations_unique');
            $table->index(['marketer_id', 'status'], 'm_ta_c_invitations_status_index');
            $table->index(['travel_agency_campaign_offer_id', 'status'], 'ta_c_invitations_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_campaign_invitations');
    }
};
