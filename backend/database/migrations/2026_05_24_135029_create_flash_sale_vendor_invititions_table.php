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
         * Table: flash_sale_vendor_invitations Controls which vendors are invited to participate. When eligible_seller_tiers on the flash sale restricts who can join, this table holds the actual invitation records. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()flash_sale_idUUID FK→flash_salesNOseller_idUUID FK→seller_profilesNOinvitation_typeVARCHAR(20)NOautoauto = matched tier/category rules. manual = admin specifically invitedstatusVARCHAR(20)NOpendingpending, accepted, declined, submittedinvited_atTIMESTAMPTZNOnow()notified_atTIMESTAMPTZYESNULLWhen email/push was sentresponded_atTIMESTAMPTZYESNULLdecline_reasonTEXTYESNULLOptional vendor feedbackslots_allocatedINTYESNULLAdmin can reserve X slots for this vendorcreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sale_vendor_invititions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flash_sale_id')->index();
            $table->uuid('vendor_id')->index();
            $table->enum('invitation_type', ['auto', 'manual']);
            $table->enum('status', ['pending', 'accepted', 'declined', 'submitted'])->default('pending');
            $table->timestampTz('invited_at')->useCurrent();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->integer('slots_allocated')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_vendor_invititions');
    }
};
