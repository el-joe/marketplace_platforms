<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_admins', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('role');
        });

        DB::table('vendor_admins')->where('role', 'owner')->update(['is_owner' => true]);

        Schema::table('vendor_admins', function (Blueprint $table) {
            $table->string('role', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_admins', function (Blueprint $table) {
            $table->enum('role', ['owner', 'manager', 'staff'])->default('owner')->change();
        });

        Schema::table('vendor_admins', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
