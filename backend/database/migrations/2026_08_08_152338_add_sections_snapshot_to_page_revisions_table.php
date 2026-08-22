<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->json('sections_snapshot')->nullable()->after('blocks_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropColumn('sections_snapshot');
        });
    }
};
