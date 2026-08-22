<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // page_sections: add layout type (stack = default, columns = horizontal grid)
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->string('layout', 20)->default('stack')->after('max_width');
            // layout: 'stack'   = blocks stacked vertically (default, all existing)
            //         'columns' = blocks arranged in a horizontal grid
            $table->string('columns_config', 100)->nullable()->after('layout');
            // columns_config: JSON string e.g. '{"columns":3,"gaps":"1/3 1/3 1/3"}'
        });

        // page_blocks: add column_span for use inside column-layout sections
        Schema::table('page_blocks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('column_index')->default(0)->after('section_id');
            // column_index: which column this block belongs to (0, 1, 2 …)
            // Only used when parent section.layout = 'columns'
        });
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->dropColumn(['layout', 'columns_config']);
        });
        Schema::table('page_blocks', function (Blueprint $table): void {
            $table->dropColumn('column_index');
        });
    }
};
