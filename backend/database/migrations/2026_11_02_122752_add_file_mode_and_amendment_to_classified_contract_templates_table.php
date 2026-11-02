<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates can be written in the editor or uploaded as pdf/docx files, and a platform-wide
     * Seller Agreement scope is added. Amendment settings control how a new version reaches vendors.
     */
    public function up(): void
    {
        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->enum('category_scope', ['classified', 'product', 'platform'])->default('classified')->change();
            $table->enum('content_mode', ['editor', 'file'])->default('editor')->after('name');
            $table->json('files')->nullable()->after('content_ar')->comment('Uploaded documents keyed by language: path, name, mime, size, sha256, pdf_path.');
            $table->enum('acceptance_mode', ['click_wrap', 'notice'])->default('click_wrap')->after('is_published')->comment('click_wrap: vendors must accept to continue. notice: effective after notice_days, continued use is acceptance.');
            $table->unsignedSmallInteger('notice_days')->default(14)->after('acceptance_mode');
            $table->timestamp('effective_at')->nullable()->after('notice_days');
            $table->timestamp('published_at')->nullable()->after('effective_at');
            $table->text('change_summary')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->dropColumn(['content_mode', 'files', 'acceptance_mode', 'notice_days', 'effective_at', 'published_at', 'change_summary']);
        });
    }
};
