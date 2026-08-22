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
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('travel_agency_id');
        });

        // Backfill any existing rows (defensive — table is expected to be empty)
        DB::table('travel_packages')->orderBy('id')->each(function ($row) {
            $base = Str::slug($row->title_en ?? 'package');
            $usedSlugs = DB::table('travel_packages')->pluck('slug')->filter()->all();

            do {
                $slug = $base . '-' . Str::lower(Str::random(6));
            } while (in_array($slug, $usedSlugs, true));

            $usedSlugs[] = $slug;

            DB::table('travel_packages')
                ->where('id', $row->id)
                ->update(['slug' => $slug]);
        });

        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
