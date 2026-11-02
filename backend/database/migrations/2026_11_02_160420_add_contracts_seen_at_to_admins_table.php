<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each admin last opened the contract signatures page. Drives the "new signatures" sidebar badge.
     * Existing admins start at now, so only signatures made after this release count as new.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->timestamp('contracts_seen_at')->nullable();
        });

        DB::table('admins')->update(['contracts_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('contracts_seen_at');
        });
    }
};
