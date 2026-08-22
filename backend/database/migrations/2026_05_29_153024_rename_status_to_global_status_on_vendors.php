<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE vendors CHANGE `status` `global_status`
             ENUM('pending','active','inactive','suspended','rejected','blacklisted','under_review')
             NOT NULL DEFAULT 'active'"
        );

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'account_manager_admin_id')) {
                $table->uuid('account_manager_admin_id')->nullable()->after('approved_by_admin_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'account_manager_admin_id')) {
                $table->dropColumn('account_manager_admin_id');
            }
        });

        DB::statement(
            "ALTER TABLE vendors CHANGE `global_status` `status`
             ENUM('active','inactive','suspended','rejected')
             NOT NULL DEFAULT 'active'"
        );
    }
};
