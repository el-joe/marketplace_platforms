<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('has_authenticity_guarantee')->default(false);
            $table->unsignedInteger('manufacturer_warranty_months')->nullable();
            $table->text('authenticity_notes_en')->nullable();
            $table->text('authenticity_notes_ar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'has_authenticity_guarantee',
                'manufacturer_warranty_months',
                'authenticity_notes_en',
                'authenticity_notes_ar',
            ]);
        });
    }
};
