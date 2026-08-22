<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_support_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_support_collection_id')
                ->constrained('ad_support_collections')->restrictOnDelete();
            $table->foreignUuid('author_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable()
                ->comment('Short summary — also used as the meta description on the portal');
            $table->longText('body')
                ->comment('Full article body — HTML from a rich text editor');

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable()
                ->comment('Set automatically the first time status becomes published');

            $table->boolean('is_featured')->default(false)
                ->comment('The one article linked from the Knowledge Hub home page "New to ads?" callout — enforce single featured article at app layer');
            $table->json('related_article_ids')->nullable()
                ->comment('Manually curated related-articles list — array of ad_support_articles ids');
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['ad_support_collection_id', 'status']);
            $table->index(['is_featured', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_support_articles');
    }
};
