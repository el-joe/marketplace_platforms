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
         * Table: order_status_history Audit of every state change. ColumnTypeDescriptionidUUID PKorder_idUUID FK → orders.id NULLsub_order_idUUID FK → sub_orders.id NULLOne must be setfrom_statusVARCHAR(50)to_statusVARCHAR(50)changed_by_user_idUUID FK → users.id NULLNULL for system changesreasonTEXT NULLmetadataJSONB NULLExtra contextcreated_atTIMESTAMP
         */
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable()->index();
            $table->uuid('sub_order_id')->nullable()->index();
            $table->string('from_status', 50);
            $table->string('to_status', 50);
            $table->uuid('changed_by_admin_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
