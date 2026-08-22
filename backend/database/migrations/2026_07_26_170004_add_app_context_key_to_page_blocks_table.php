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
        Schema::table('page_blocks', function (Blueprint $table) {
            // Soft reference to app_contexts.key — no FK, mirrors pages.app_context_key.
            // NULL = block is shown regardless of the requesting app context.
            $table->string('app_context_key', 50)->nullable()->after('block_type')
                ->comment('Restricts block to an app context (e.g. nawy_now). NULL = shown in every context.');
            $table->index('app_context_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropIndex(['app_context_key']);
            $table->dropColumn('app_context_key');
        });
    }
};
