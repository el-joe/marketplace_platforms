<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $t) {
            $t->string('specialty_en', 150)->nullable();
            $t->string('specialty_ar', 150)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $t) {
            $t->dropColumn(['specialty_en', 'specialty_ar']);
        });
    }
};
