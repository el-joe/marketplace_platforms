<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FooterLink;
use Illuminate\Database\Seeder;

class FooterLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $group => $rows) {
            foreach ($rows as $i => $row) {
                FooterLink::updateOrCreate(
                    [
                        'group' => $group,
                        'platform' => $row['platform'] ?? null,
                        'label_en' => $row['label_en'] ?? null,
                    ],
                    array_merge($row, [
                        'group' => $group,
                        'sort_order' => $i + 1,
                        'is_active' => true,
                    ])
                );
            }
        }

        $this->seedFooterCategories();
    }

    /**
     * Show the first handful of active parent categories in the footer, so
     * the footer isn't empty out of the box. Admins can change this per
     * category from Admin > Categories afterwards.
     */
    private function seedFooterCategories(): void
    {
        Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('lft')
            ->limit(9)
            ->get()
            ->each(fn (Category $category) => $category->update(['show_in_footer' => true]));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function definitions(): array
    {
        return [
            'social' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com', 'icon_path' => '/images/facebook-social.svg'],
                ['platform' => 'x', 'url' => 'https://x.com', 'icon_path' => '/images/Twitter-X-social.svg'],
            ],

            // Privacy / Terms (and the other legal/company pages) are admin-managed Static Pages
            // now (StaticPageSeeder, exposed as `static_pages` in the footer payload), so no
            // placeholder https://example.com/... links are seeded here any more. Real bottom-bar
            // links can still be added from Admin > Footer Settings.
            'bottom_nav' => [],

            'app_store' => [
                [
                    'platform' => 'app_store',
                    'label_en' => 'App Store',
                    'url' => 'https://apps.apple.com',
                    'icon_path' => 'https://f.nooncdn.com/s/app/com/common/images/logos/app-store.svg',
                ],
                [
                    'platform' => 'google_play',
                    'label_en' => 'Google Play',
                    'url' => 'https://play.google.com',
                    'icon_path' => 'https://f.nooncdn.com/s/app/com/common/images/logos/google-play.svg',
                ],
                [
                    'platform' => 'huawei',
                    'label_en' => 'Huawei AppGallery',
                    'url' => 'https://appgallery.huawei.com',
                    'icon_path' => 'https://f.nooncdn.com/s/app/com/noon/images/Huawei-icon.png',
                ],
            ],

            'payment_method' => [
                ['label_en' => 'Mastercard', 'icon_path' => 'https://f.nooncdn.com/s/app/com/noon/design-system/payment-methods-v2/mastercard-v3.svg'],
                ['label_en' => 'Visa', 'icon_path' => 'https://f.nooncdn.com/s/app/com/noon/design-system/payment-methods-v2/visa-v3.svg'],
                ['label_en' => 'Tabby', 'icon_path' => 'https://f.nooncdn.com/s/app/com/noon/design-system/payment-methods-v2/tabby-3.svg'],
                ['label_en' => 'Tamara', 'icon_path' => 'https://f.nooncdn.com/s/app/com/noon/design-system/payment-methods-v2/tamara-3.svg'],
            ],
        ];
    }
}
