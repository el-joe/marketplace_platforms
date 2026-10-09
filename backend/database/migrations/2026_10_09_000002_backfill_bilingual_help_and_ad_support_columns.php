<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: Help Center and Ad Support records were created with only
 * the legacy single-language columns (name/description/title/excerpt/body),
 * leaving the bilingual *_en / *_ar columns NULL. The admin forms edit the
 * *_en / *_ar columns, so they opened empty and the portal showed the same
 * text in both languages.
 *
 * For every legacy value whose *_en and *_ar are both NULL, copy it into
 * *_ar when it contains Arabic script, otherwise into *_en. Legacy columns
 * are left untouched, so this is lossless.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const FIELDS = [
        'help_center_categories' => ['name', 'description'],
        'help_center_articles' => ['title', 'excerpt', 'body'],
        'ad_support_collections' => ['name', 'description'],
        'ad_support_articles' => ['title', 'excerpt', 'body'],
    ];

    public function up(): void
    {
        foreach (self::FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                DB::table($table)
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->whereNull("{$field}_en")
                    ->whereNull("{$field}_ar")
                    ->select(['id', $field])
                    ->chunkById(200, function ($rows) use ($table, $field) {
                        foreach ($rows as $row) {
                            $target = preg_match('/\p{Arabic}/u', $row->{$field}) ? "{$field}_ar" : "{$field}_en";

                            DB::table($table)->where('id', $row->id)->update([$target => $row->{$field}]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        // Lossless backfill of empty columns: nothing to roll back.
    }
};
