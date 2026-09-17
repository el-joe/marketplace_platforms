<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-10 task 2: categories had no way to mark themselves
 * non-returnable (hygiene items etc). Backfill-safe: default true means
 * every existing (populated) category keeps behaving as returnable —
 * identical to today's behaviour — until an admin opts a category out.
 *
 * A country-level override was asked for but there is no existing
 * mechanism (table/column) for country-scoped category flags to hang one
 * on, so it is intentionally NOT implemented here — only the category-level
 * flag exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_returnable')->default(true)->after('return_window_days');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_returnable');
        });
    }
};
