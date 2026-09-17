<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-19 task 3.
 *
 * This MySQL instance (8.0.46) has the built-in `ngram` full-text parser
 * plugin ACTIVE (`SELECT * FROM information_schema.plugins WHERE
 * plugin_name = 'ngram'`), which is the correct choice for Arabic/CJK text
 * because the built-in parser only tokenizes on whitespace/punctuation and
 * Arabic words are not reliably whitespace-delimited into search-sized
 * tokens the way English is. Laravel Scout/Meilisearch is NOT wired up
 * anywhere in this codebase (no `laravel/scout` dependency, no
 * `searchable()` usage) — see composer.json — so per the spec's "or
 * Laravel Scout if available" this falls back to a plain MySQL FULLTEXT
 * index with the ngram parser.
 *
 * ngram_token_size defaults to 2, which is appropriate for Arabic; it is a
 * server-level setting (`ngram_token_size`), not something a migration can
 * change per-index. The existing short-query (<3 chars) LIKE fallback in
 * SearchService::adminListingSearch() is left untouched (task 3: "keep the
 * LIKE fallback for queries under 3 characters").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            // The base schema dump already ships a FULLTEXT index with this
            // same name but the wrong columns (name_en, name_ar, short_desc_en,
            // model_number) and no ngram parser. Replace it rather than erroring
            // on the duplicate name.
            $existing = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_fulltext_search'");
            if (! empty($existing)) {
                DB::statement('ALTER TABLE products DROP INDEX products_fulltext_search');
            }

            DB::statement(
                'ALTER TABLE products ADD FULLTEXT INDEX products_fulltext_search '
                .'(name_en, name_ar, short_desc_en, short_desc_ar) WITH PARSER ngram'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            DB::statement('ALTER TABLE products DROP INDEX products_fulltext_search');
        }
    }
};
