<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Append-only — never updated or deleted
    public function up(): void
    {
        Schema::create('page_block_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('page_id')->index(); // denormalized for fast page-level history
            $table->integer('revision_number'); // starts at 1, increments per block
            $table->json('config_snapshot');
            $table->boolean('is_visible_snapshot');
            $table->integer('position_snapshot');
            $table->uuid('changed_by_admin_id')->index();
            $table->string('change_reason', 255)->nullable();
            $table->string('change_type', 20); // created|config_updated|moved|visibility_changed|deleted
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — append-only

            $table->index(['page_block_id', 'revision_number']);
            $table->index(['page_id', 'created_at']);
            $table->index(['changed_by_admin_id', 'created_at']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_revisions');
    }
};
