<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookable_units MODIFY status ENUM('draft','active','paused','archived','rejected') NOT NULL DEFAULT 'draft'");

        Schema::table('bookable_units', function (Blueprint $table) {
            $table->uuid('rejected_by_admin_id')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_admin_id');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreign('rejected_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bookable_units', function (Blueprint $table) {
            $table->dropForeign(['rejected_by_admin_id']);
            $table->dropColumn(['rejected_by_admin_id', 'rejected_at', 'rejection_reason']);
        });

        DB::statement("ALTER TABLE bookable_units MODIFY status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft'");
    }
};
