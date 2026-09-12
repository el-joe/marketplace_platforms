<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->decimal('hip_cm', 5, 1)->nullable()->after('waist_cm')
                ->comment('محيط الحوض — Hip circumference in cm');
            $table->decimal('item_length_cm', 5, 1)->nullable()->after('height_cm')
                ->comment('طول الملابس — Full garment length in cm');
            $table->decimal('sleeve_from_neck_cm', 5, 1)->nullable()->after('item_length_cm')
                ->comment('الكم من الرقبة — Sleeve length measured from neck');
            $table->decimal('sleeve_from_shoulder_cm', 5, 1)->nullable()->after('sleeve_from_neck_cm')
                ->comment('الكم من الكتف — Sleeve length measured from shoulder');
            $table->decimal('sleeve_width_cm', 5, 1)->nullable()->after('sleeve_from_shoulder_cm')
                ->comment('عرض الكم — Sleeve width / arms width in cm');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'hip_cm',
                'item_length_cm',
                'sleeve_from_neck_cm',
                'sleeve_from_shoulder_cm',
                'sleeve_width_cm',
            ]);
        });
    }
};
