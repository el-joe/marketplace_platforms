<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('locale', 2)->default('ar')->after('country_id');
            $table->boolean('marketing_email_enabled')->default(true)->after('locale');
            $table->boolean('marketing_sms_enabled')->default(true)->after('marketing_email_enabled');
            $table->boolean('marketing_whatsapp_enabled')->default(true)->after('marketing_sms_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'locale',
                'marketing_email_enabled',
                'marketing_sms_enabled',
                'marketing_whatsapp_enabled',
            ]);
        });
    }
};
