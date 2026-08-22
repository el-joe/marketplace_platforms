<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Append-only — snapshots entire page layout on each publish event
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id')->index();
            $table->integer('version'); // matches pages.version at time of publish
            $table->json('blocks_snapshot'); // full array of all blocks with configs
            $table->uuid('published_by_admin_id')->index();
            $table->string('publish_reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — append-only

            $table->index(['page_id', 'version']);

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
