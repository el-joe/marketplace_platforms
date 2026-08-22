<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id')->index();
            $table->uuid('section_id')->nullable()->index();
            $table->string('block_type', 50)->index(); // matches block_types.code
            $table->integer('position')->default(0);
            $table->json('config')->nullable(); // block-specific settings
            $table->boolean('is_visible')->default(true);
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->string('device_target', 10)->default('all'); // all|desktop|mobile|app
            $table->string('audience', 20)->default('all');      // all|guest|logged_in|vip|...
            $table->uuid('country_override')->nullable()->index(); // NULL = inherits page country
            $table->uuid('ab_test_id')->nullable()->index();
            $table->char('ab_variant', 1)->nullable(); // a|b
            $table->integer('cache_ttl_seconds')->default(60);
            $table->uuid('created_by_admin_id')->index();
            $table->uuid('updated_by_admin_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['page_id', 'position']);
            $table->index(['section_id', 'position']);
            $table->index(['is_visible', 'visible_from', 'visible_until']);
            $table->index('deleted_at');

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('page_sections')->onDelete('set null');
            $table->foreign('ab_test_id')->references('id')->on('ab_tests')->onDelete('set null');
            $table->foreign('country_override')->references('id')->on('countries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
