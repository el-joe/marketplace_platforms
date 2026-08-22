<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('site_code', 20)->nullable()->unique()->after('iso_code_3');
            $table->string('site_domain', 100)->nullable()->after('site_code');
            $table->string('default_locale', 10)->nullable()->default('en')->after('currency_code');
            $table->string('timezone', 100)->nullable()->default('UTC')->after('default_locale');
            $table->boolean('is_launched')->default(false)->after('is_active');
            $table->boolean('cod_available')->default(false)->after('is_launched');
            $table->timestamp('launched_at')->nullable()->after('cod_available');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'site_code',
                'site_domain',
                'default_locale',
                'timezone',
                'is_launched',
                'cod_available',
                'launched_at',
            ]);
        });
    }
};
