<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->string('clothing_size', 20)->nullable()->after('can_self_edit_ad_price')
                ->comment('General clothing size label, e.g. M, L, XL. Influencer marketers only.');
            $table->string('shirt_size', 20)->nullable()->after('clothing_size');
            $table->string('pants_size', 20)->nullable()->after('shirt_size');
            $table->string('dress_size', 20)->nullable()->after('pants_size');
            $table->string('abaya_size', 20)->nullable()->after('dress_size');
            $table->string('shoe_size', 10)->nullable()->after('abaya_size');
            $table->enum('shoe_size_system', ['EU', 'US', 'UK'])->nullable()->after('shoe_size');
            $table->decimal('chest_cm', 5, 1)->nullable()->after('shoe_size_system');
            $table->decimal('waist_cm', 5, 1)->nullable()->after('chest_cm');
            $table->decimal('height_cm', 5, 1)->nullable()->after('waist_cm');
            $table->text('measurements_notes')->nullable()->after('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'clothing_size', 'shirt_size', 'pants_size', 'dress_size', 'abaya_size',
                'shoe_size', 'shoe_size_system', 'chest_cm', 'waist_cm', 'height_cm', 'measurements_notes',
            ]);
        });
    }
};
