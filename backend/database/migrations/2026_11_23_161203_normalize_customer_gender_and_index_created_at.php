<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WS11 (#14): customers.gender already existed as a free VARCHAR(20) that the
 * profile API also filled with "prefer_not_to_say". Gender is now optional
 * male|female (NULL = not specified), matching vendors.gender.
 *
 * Also indexes customers.created_at: the admin customers list sorts by it by
 * default and filters on it ("Joined between").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->whereNotNull('gender')
            ->whereNotIn('gender', ['male', 'female'])
            ->update(['gender' => null]);

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->change();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->string('gender', 20)->nullable()->change();
        });
    }
};
