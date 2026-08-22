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
         * Table: flash_sale_submission_history Every status change on a submission. Append-only audit trail. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()flash_sale_submission_idUUID FK→flash_sale_submissionsNOfrom_statusVARCHAR(30)NOto_statusVARCHAR(30)NOchanged_by_user_idUUID FK→usersNOAdmin or vendorchanged_by_roleVARCHAR(20)NOadmin, vendor, systemreasonTEXTYESNULLcreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sale_submission_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flash_sale_submission_id')->index();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->uuid('changed_by_user_id')->index();
            $table->enum('changed_by_role', ['admin', 'vendor', 'system']);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_submission_histories');
    }
};
