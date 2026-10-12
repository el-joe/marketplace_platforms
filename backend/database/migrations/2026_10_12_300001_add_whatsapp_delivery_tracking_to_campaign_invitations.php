<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->unsignedSmallInteger('whatsapp_attempts')->default(0)->after('whatsapp_sent_at');
            $table->string('whatsapp_last_error', 500)->nullable()->after('whatsapp_attempts');
            $table->timestamp('whatsapp_failed_at')->nullable()->after('whatsapp_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_attempts', 'whatsapp_last_error', 'whatsapp_failed_at']);
        });
    }
};
