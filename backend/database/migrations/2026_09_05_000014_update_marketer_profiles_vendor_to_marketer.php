<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            // Drop the old foreign key constraint first
            $table->dropForeign(['vendor_id']);

            // Rename the column
            $table->renameColumn('vendor_id', 'marketer_id');
        });

        Schema::table('marketer_profiles', function (Blueprint $table) {
            // Re-add as FK to the new marketers table
            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropForeign(['marketer_id']);
            $table->renameColumn('marketer_id', 'vendor_id');
        });

        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });
    }
};
