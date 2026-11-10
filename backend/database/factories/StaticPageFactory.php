<?php

namespace Database\Factories;

use App\Models\StaticPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaticPage>
 */
class StaticPageFactory extends Factory
{
    protected $model = StaticPage::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title_en' => Str::title($title),
            'title_ar' => 'صفحة '.fake()->word(),
            'content_en' => '<p>'.fake()->paragraph().'</p>',
            'content_ar' => '<p>محتوى تجريبي.</p>',
            'audience' => StaticPage::AUDIENCE_BOTH,
            'show_in_footer' => false,
            'footer_group' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function portal(): static
    {
        return $this->state(['audience' => StaticPage::AUDIENCE_PORTAL]);
    }

    public function storefront(): static
    {
        return $this->state(['audience' => StaticPage::AUDIENCE_STOREFRONT]);
    }

    public function inFooter(string $group = StaticPage::FOOTER_GROUP_POLICIES, int $sortOrder = 0): static
    {
        return $this->state(['show_in_footer' => true, 'footer_group' => $group, 'sort_order' => $sortOrder]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
