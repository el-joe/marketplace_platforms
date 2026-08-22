<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Soft reference to app_contexts.key — no FK, so contexts can be
            // added/removed without migrating every historic page row.
            $table->string('app_context_key', 50)->nullable()->after('page_type')
                ->comment('Links page to an app context (main, nawy_now, classifieds, travel). NULL = general/admin use.');
            $table->index('app_context_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['app_context_key']);
            $table->dropColumn('app_context_key');
        });
    }
};
