<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropForeign(['marketer_vendor_id']);
            $table->renameColumn('marketer_vendor_id', 'marketer_id');
        });

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropForeign(['marketer_id']);
            $table->renameColumn('marketer_id', 'marketer_vendor_id');
        });

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->foreign('marketer_vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });
    }
};
