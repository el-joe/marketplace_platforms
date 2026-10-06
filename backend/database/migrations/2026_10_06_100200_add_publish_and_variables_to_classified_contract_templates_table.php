<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates now cover product categories too, and drafts are only used once published.
     */
    public function up(): void
    {
        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->enum('category_scope', ['classified', 'product'])->default('classified')->after('classified_category_id');
            $table->uuid('product_category_id')->nullable()->after('category_scope');
            $table->boolean('is_published')->default(false)->after('updated_at')->comment('Only published templates are used to render vendor enrollment contracts.');
            $table->json('variables_schema')->nullable()->after('is_published')->comment('Variable keys this template references, validated on save.');

            $table->foreign('product_category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn(['category_scope', 'product_category_id', 'is_published', 'variables_schema']);
        });
    }
};
