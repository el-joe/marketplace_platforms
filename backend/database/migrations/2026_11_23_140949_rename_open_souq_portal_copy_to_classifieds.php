<?php

use App\Models\PortalContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Client feedback #20: the portal "Open Souq" (السوق المفتوح) section is renamed "Classifieds" (الإعلانات المبوبة),
 * and Travel & Tourism is presented under it.
 *
 * Only copy that still holds the old shipped default is rewritten (each language separately, every country row),
 * so wording an admin already changed in Portal Content is kept.
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: array{string, string}, 4: array{string, string}}>
     *                                                                                                       [page, block, field, [old en, old ar], [new en, new ar]]
     */
    private const RENAMES = [
        ['nav', 'section_open_souq', 'benefits',
            ['Why sell on Open Souq', 'لماذا تبيع على السوق المفتوح'],
            ['Why list on Classifieds', 'لماذا تنشر في الإعلانات المبوبة']],
        ['join', 'card_open_souq', 'title',
            ['Open Souq', 'السوق المفتوح'],
            ['Classifieds', 'الإعلانات المبوبة']],
        ['join', 'card_open_souq', 'description',
            ['List across our classified categories — real estate, cars, chalets and more.', 'أدرج إعلاناتك في فئات السوق المفتوح — العقارات والسيارات والشاليهات وغيرها.'],
            ['List in our classified sections — real estate, cars, travel & tourism and more.', 'انشر إعلاناتك في أقسام الإعلانات المبوبة — العقارات والسيارات والسفر والسياحة وغيرها.']],
        ['join', 'card_open_souq', 'image',
            ['Open Souq', 'السوق المفتوح'],
            ['Classifieds', 'الإعلانات المبوبة']],
        ['open-souq', 'meta', 'title',
            ['Sell on Open Souq — Nawy', 'بِع على السوق المفتوح — ناوي'],
            ['List on Classifieds — Nawy', 'انشر في الإعلانات المبوبة — ناوي']],
        ['open-souq', 'meta', 'description',
            ['List across our classified categories — real estate, cars, chalets and more — and reach buyers across the region.', 'أدرج إعلاناتك في فئات السوق المفتوح — العقارات والسيارات والشاليهات وغيرها — وصِل إلى المشترين في جميع أنحاء المنطقة.'],
            ['List in our classified sections — real estate, cars, travel & tourism and more — and reach buyers across the region.', 'انشر إعلاناتك في أقسام الإعلانات المبوبة — العقارات والسيارات والسفر والسياحة وغيرها — وصِل إلى المشترين في جميع أنحاء المنطقة.']],
        ['open-souq', 'hero', 'eyebrow',
            ['Open Souq', 'السوق المفتوح'],
            ['Classifieds', 'الإعلانات المبوبة']],
        ['open-souq', 'hero', 'subtitle',
            ['Open Souq is where individuals and businesses list across our classified categories — real estate, cars, chalets and more.', 'السوق المفتوح هو المكان الذي يدرج فيه الأفراد والشركات إعلاناتهم في فئاتنا المبوبة — العقارات والسيارات والشاليهات وغيرها.'],
            ['Classifieds is where individuals and businesses list across our sections — real estate, cars, travel & tourism and more.', 'الإعلانات المبوبة هي المكان الذي ينشر فيه الأفراد والشركات إعلاناتهم في أقسامنا — العقارات والسيارات والسفر والسياحة وغيرها.']],
        ['open-souq', 'hero', 'photo',
            ['A seller listing items on Open Souq', 'بائع يدرج إعلاناته على السوق المفتوح'],
            ['A seller publishing classified ads', 'بائع ينشر إعلاناته المبوبة']],
        ['open-souq', 'benefits', 'title',
            ['Why sell on Open Souq?', 'لماذا تبيع على السوق المفتوح؟'],
            ['Why list on Classifieds?', 'لماذا تنشر في الإعلانات المبوبة؟']],
    ];

    public function up(): void
    {
        $this->apply(fn (array $old, array $new) => [$old, $new]);
    }

    public function down(): void
    {
        $this->apply(fn (array $old, array $new) => [$new, $old]);
    }

    /**
     * @param  callable(array{string, string}, array{string, string}): array{0: array{string, string}, 1: array{string, string}}  $direction
     */
    private function apply(callable $direction): void
    {
        foreach (self::RENAMES as [$page, $block, $field, $old, $new]) {
            [$from, $to] = $direction($old, $new);

            foreach (['value_en' => 0, 'value_ar' => 1] as $column => $i) {
                PortalContent::query()
                    ->where('page_key', $page)
                    ->where('block_key', $block)
                    ->where('field_key', $field)
                    ->where($column, $from[$i])
                    ->update([$column => $to[$i]]);
            }
        }

        foreach (array_unique(array_column(self::RENAMES, 0)) as $page) {
            PortalContent::flush($page);
        }
    }
};
