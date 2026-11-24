<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client retest R9 (#7): the Page Builder "Category Pills" description still said an empty block shows the
 * top-level categories; since WS6 it shows the Featured categories first. Only the shipped default sentence is
 * rewritten (per language), so a description an admin already edited is kept. Page/block data is not touched.
 */
return new class extends Migration
{
    private const OLD = [
        'description_en' => 'Manually select which categories to show, or leave empty to auto-show the top-level categories.',
        'description_ar' => 'اختر يدويًا الفئات المراد عرضها، أو اتركها فارغة لعرض الفئات الرئيسية تلقائيًا.',
    ];

    private const NEW = [
        'description_en' => 'Manually select which categories to show, or leave empty to show the categories marked Featured (Categories → Featured), falling back to the top-level categories when none are featured.',
        'description_ar' => 'اختر يدويًا الفئات المراد عرضها، أو اتركها فارغة لعرض الفئات المعلّمة كمميزة (الفئات ← مميز)، مع الرجوع إلى الفئات الرئيسية إذا لم توجد فئات مميزة.',
    ];

    public function up(): void
    {
        $this->rewrite(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW, self::OLD);
    }

    /**
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     */
    private function rewrite(array $from, array $to): void
    {
        foreach ($from as $column => $sentence) {
            $row = DB::table('block_types')->where('code', 'category_pills')->first(['id', $column]);

            if ($row === null || ! str_contains((string) $row->{$column}, $sentence)) {
                continue;
            }

            DB::table('block_types')->where('id', $row->id)
                ->update([$column => str_replace($sentence, $to[$column], $row->{$column})]);
        }
    }
};
