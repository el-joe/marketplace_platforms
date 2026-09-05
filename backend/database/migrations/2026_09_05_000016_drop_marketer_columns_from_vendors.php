<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['marketer_type', 'whatsapp_for_campaigns']);
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('marketer_type', ['influencer', 'affiliate'])->nullable()->comment('null = vendor only; influencer/affiliate = vendor+marketer');
            $table->string('whatsapp_for_campaigns', 30)->nullable()->comment('WhatsApp number for campaign invitation messages');
        });
    }
};
