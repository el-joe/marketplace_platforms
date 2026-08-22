<?php

namespace Database\Seeders;

use App\Models\TravelCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TravelCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_en' => 'Beach & Resorts',       'name_ar' => 'الشواطئ والمنتجعات',    'slug' => 'beach-resorts',        'icon' => 'beach',      'sort_order' => 1],
            ['name_en' => 'Adventure & Outdoor',   'name_ar' => 'المغامرة والهواء الطلق', 'slug' => 'adventure-outdoor',    'icon' => 'adventure',  'sort_order' => 2],
            ['name_en' => 'Religious & Pilgrimage', 'name_ar' => 'الرحلات الدينية والحج', 'slug' => 'religious-pilgrimage', 'icon' => 'religious',  'sort_order' => 3],
            ['name_en' => 'City Breaks',            'name_ar' => 'عطل المدن',             'slug' => 'city-breaks',          'icon' => 'city',       'sort_order' => 4],
            ['name_en' => 'Family Packages',        'name_ar' => 'الباقات العائلية',      'slug' => 'family-packages',      'icon' => 'family',     'sort_order' => 5],
            ['name_en' => 'Honeymoon & Romance',    'name_ar' => 'شهر العسل والرومانسية', 'slug' => 'honeymoon-romance',    'icon' => 'honeymoon',  'sort_order' => 6],
            ['name_en' => 'Cruises',                'name_ar' => 'رحلات الإبحار',         'slug' => 'cruises',              'icon' => 'cruise',     'sort_order' => 7],
            ['name_en' => 'Desert Safari',          'name_ar' => 'سفاري الصحراء',         'slug' => 'desert-safari',        'icon' => 'desert',     'sort_order' => 8],
        ];

        foreach ($categories as $data) {
            TravelCategory::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
