<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('listing_number');
        });

        // Backfill any existing rows (defensive — table is expected to be empty)
        DB::table('classified_listings')->orderBy('id')->each(function ($row) {
            $base = Str::slug($row->title_en ?? 'listing');
            $suffix = 'clsf-' . Str::lower($row->listing_number ?? $row->id);
            DB::table('classified_listings')
                ->where('id', $row->id)
                ->update(['slug' => $base . '-' . $suffix]);
        });

        Schema::table('classified_listings', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('classified_listings', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
