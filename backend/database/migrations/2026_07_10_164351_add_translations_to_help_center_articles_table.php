<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_center_articles', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->string('title_ar')->nullable()->after('title_en');
            $table->string('excerpt_en')->nullable()->after('excerpt');
            $table->string('excerpt_ar')->nullable()->after('excerpt_en');
            $table->longText('body_en')->nullable()->after('body');
            $table->longText('body_ar')->nullable()->after('body_en');
        });

        DB::table('help_center_articles')->update([
            'title_en' => DB::raw('title'),
            'title_ar' => DB::raw('title'),
            'excerpt_en' => DB::raw('excerpt'),
            'excerpt_ar' => DB::raw('excerpt'),
            'body_en' => DB::raw('body'),
            'body_ar' => DB::raw('body'),
        ]);
    }

    public function down(): void
    {
        Schema::table('help_center_articles', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar']);
        });
    }
};
