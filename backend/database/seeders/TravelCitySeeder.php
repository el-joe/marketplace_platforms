<?php

namespace Database\Seeders;

use App\Models\TravelCity;
use App\Models\TravelCountry;
use Illuminate\Database\Seeder;

/**
 * Seeds major cities per travel country.
 * - GCC/MENA countries: comprehensive coverage (primary market)
 * - Major global tourist destinations: capital + top tourist/business cities
 * - Very small/micro-states: capital only
 * Safe to re-run: keyed on (travel_country_id, name_en) via firstOrCreate.
 */
class TravelCitySeeder extends Seeder
{
    public function run(): void
    {
        $index = $this->cityIndex();

        foreach ($index as $iso2 => $cities) {
            $country = TravelCountry::where('iso_code_2', $iso2)->first();
            if (! $country) {
                continue;
            }

            foreach ($cities as $city) {
                TravelCity::firstOrCreate(
                    [
                        'travel_country_id' => $country->id,
                        'name_en'           => $city['name_en'],
                    ],
                    $city + ['travel_country_id' => $country->id],
                );
            }
        }
    }

    private function cityIndex(): array
    {
        return [
            // ── GCC ──────────────────────────────────────────────────────────

            'AE' => [
                ['name_en' => 'Dubai',            'name_ar' => 'دبي',              'latitude' => 25.2048,  'longitude' => 55.2708],
                ['name_en' => 'Abu Dhabi',         'name_ar' => 'أبوظبي',           'latitude' => 24.4539,  'longitude' => 54.3773],
                ['name_en' => 'Sharjah',           'name_ar' => 'الشارقة',          'latitude' => 25.3462,  'longitude' => 55.4211],
                ['name_en' => 'Ajman',             'name_ar' => 'عجمان',            'latitude' => 25.4052,  'longitude' => 55.5136],
                ['name_en' => 'Ras Al Khaimah',    'name_ar' => 'رأس الخيمة',       'latitude' => 25.7895,  'longitude' => 55.9432],
                ['name_en' => 'Fujairah',          'name_ar' => 'الفجيرة',          'latitude' => 25.1288,  'longitude' => 56.3264],
                ['name_en' => 'Umm Al Quwain',     'name_ar' => 'أم القيوين',       'latitude' => 25.5647,  'longitude' => 55.5550],
            ],
            'SA' => [
                ['name_en' => 'Riyadh',            'name_ar' => 'الرياض',           'latitude' => 24.7136,  'longitude' => 46.6753],
                ['name_en' => 'Jeddah',            'name_ar' => 'جدة',              'latitude' => 21.4858,  'longitude' => 39.1925],
                ['name_en' => 'Mecca',             'name_ar' => 'مكة المكرمة',      'latitude' => 21.3891,  'longitude' => 39.8579],
                ['name_en' => 'Medina',            'name_ar' => 'المدينة المنورة',  'latitude' => 24.5247,  'longitude' => 39.5692],
                ['name_en' => 'Dammam',            'name_ar' => 'الدمام',           'latitude' => 26.4207,  'longitude' => 50.0888],
                ['name_en' => 'Khobar',            'name_ar' => 'الخبر',            'latitude' => 26.2172,  'longitude' => 50.1971],
                ['name_en' => 'Abha',              'name_ar' => 'أبها',             'latitude' => 18.2164,  'longitude' => 42.5053],
                ['name_en' => 'Tabuk',             'name_ar' => 'تبوك',             'latitude' => 28.3838,  'longitude' => 36.5550],
                ['name_en' => 'NEOM',              'name_ar' => 'نيوم',             'latitude' => 28.0000,  'longitude' => 35.0000],
                ['name_en' => 'AlUla',             'name_ar' => 'العُلا',           'latitude' => 26.6261,  'longitude' => 37.9219],
            ],
            'QA' => [
                ['name_en' => 'Doha',              'name_ar' => 'الدوحة',           'latitude' => 25.2854,  'longitude' => 51.5310],
                ['name_en' => 'Al Rayyan',         'name_ar' => 'الريان',           'latitude' => 25.2519,  'longitude' => 51.4231],
                ['name_en' => 'Al Wakrah',         'name_ar' => 'الوكرة',           'latitude' => 25.1674,  'longitude' => 51.5987],
            ],
            'KW' => [
                ['name_en' => 'Kuwait City',       'name_ar' => 'مدينة الكويت',     'latitude' => 29.3759,  'longitude' => 47.9774],
                ['name_en' => 'Hawalli',           'name_ar' => 'حولي',             'latitude' => 29.3349,  'longitude' => 48.0302],
                ['name_en' => 'Salmiya',           'name_ar' => 'السالمية',         'latitude' => 29.3430,  'longitude' => 48.0798],
            ],
            'BH' => [
                ['name_en' => 'Manama',            'name_ar' => 'المنامة',          'latitude' => 26.2154,  'longitude' => 50.5832],
                ['name_en' => 'Muharraq',          'name_ar' => 'المحرق',           'latitude' => 26.2677,  'longitude' => 50.6113],
                ['name_en' => 'Riffa',             'name_ar' => 'الرفاع',           'latitude' => 26.1299,  'longitude' => 50.5560],
            ],
            'OM' => [
                ['name_en' => 'Muscat',            'name_ar' => 'مسقط',             'latitude' => 23.5880,  'longitude' => 58.3829],
                ['name_en' => 'Salalah',           'name_ar' => 'صلالة',            'latitude' => 17.0151,  'longitude' => 54.0924],
                ['name_en' => 'Sohar',             'name_ar' => 'صحار',             'latitude' => 24.3473,  'longitude' => 56.7059],
                ['name_en' => 'Nizwa',             'name_ar' => 'نزوى',             'latitude' => 22.9333,  'longitude' => 57.5333],
                ['name_en' => 'Sur',               'name_ar' => 'صور',              'latitude' => 22.5686,  'longitude' => 59.5289],
            ],

            // ── Arab World (non-GCC) ────────────────────────────────────────

            'EG' => [
                ['name_en' => 'Cairo',             'name_ar' => 'القاهرة',          'latitude' => 30.0444,  'longitude' => 31.2357],
                ['name_en' => 'Alexandria',        'name_ar' => 'الإسكندرية',       'latitude' => 31.2001,  'longitude' => 29.9187],
                ['name_en' => 'Sharm El-Sheikh',   'name_ar' => 'شرم الشيخ',        'latitude' => 27.9158,  'longitude' => 34.3300],
                ['name_en' => 'Luxor',             'name_ar' => 'الأقصر',           'latitude' => 25.6872,  'longitude' => 32.6396],
                ['name_en' => 'Aswan',             'name_ar' => 'أسوان',            'latitude' => 24.0889,  'longitude' => 32.8998],
                ['name_en' => 'Hurghada',          'name_ar' => 'الغردقة',          'latitude' => 27.2579,  'longitude' => 33.8116],
                ['name_en' => 'Marsa Alam',        'name_ar' => 'مرسى علم',         'latitude' => 25.0661,  'longitude' => 34.8946],
            ],
            'JO' => [
                ['name_en' => 'Amman',             'name_ar' => 'عمّان',            'latitude' => 31.9454,  'longitude' => 35.9284],
                ['name_en' => 'Aqaba',             'name_ar' => 'العقبة',           'latitude' => 29.5267,  'longitude' => 35.0060],
                ['name_en' => 'Petra',             'name_ar' => 'البتراء',          'latitude' => 30.3285,  'longitude' => 35.4444],
                ['name_en' => 'Jerash',            'name_ar' => 'جرش',              'latitude' => 32.2742,  'longitude' => 35.8994],
                ['name_en' => 'Dead Sea',          'name_ar' => 'البحر الميت',      'latitude' => 31.5590,  'longitude' => 35.4732],
            ],
            'LB' => [
                ['name_en' => 'Beirut',            'name_ar' => 'بيروت',            'latitude' => 33.8938,  'longitude' => 35.5018],
                ['name_en' => 'Tripoli',           'name_ar' => 'طرابلس',           'latitude' => 34.4367,  'longitude' => 35.8497],
                ['name_en' => 'Byblos',            'name_ar' => 'جبيل',             'latitude' => 34.1236,  'longitude' => 35.6513],
                ['name_en' => 'Sidon',             'name_ar' => 'صيدا',             'latitude' => 33.5606,  'longitude' => 35.3714],
            ],
            'MA' => [
                ['name_en' => 'Casablanca',        'name_ar' => 'الدار البيضاء',    'latitude' => 33.5731,  'longitude' => -7.5898],
                ['name_en' => 'Marrakech',         'name_ar' => 'مراكش',            'latitude' => 31.6295,  'longitude' => -7.9811],
                ['name_en' => 'Rabat',             'name_ar' => 'الرباط',           'latitude' => 33.9716,  'longitude' => -6.8498],
                ['name_en' => 'Fez',               'name_ar' => 'فاس',              'latitude' => 34.0181,  'longitude' => -5.0078],
                ['name_en' => 'Agadir',            'name_ar' => 'أكادير',           'latitude' => 30.4278,  'longitude' => -9.5981],
                ['name_en' => 'Tangier',           'name_ar' => 'طنجة',             'latitude' => 35.7595,  'longitude' => -5.8340],
            ],
            'TN' => [
                ['name_en' => 'Tunis',             'name_ar' => 'تونس',             'latitude' => 36.8190,  'longitude' => 10.1658],
                ['name_en' => 'Djerba',            'name_ar' => 'جربة',             'latitude' => 33.8076,  'longitude' => 10.8451],
                ['name_en' => 'Sousse',            'name_ar' => 'سوسة',             'latitude' => 35.8256,  'longitude' => 10.6369],
                ['name_en' => 'Hammamet',          'name_ar' => 'الحمامات',         'latitude' => 36.4028,  'longitude' => 10.6163],
            ],
            'IQ' => [
                ['name_en' => 'Baghdad',           'name_ar' => 'بغداد',            'latitude' => 33.3128,  'longitude' => 44.3615],
                ['name_en' => 'Erbil',             'name_ar' => 'أربيل',            'latitude' => 36.1901,  'longitude' => 44.0091],
                ['name_en' => 'Basra',             'name_ar' => 'البصرة',           'latitude' => 30.5085,  'longitude' => 47.7804],
                ['name_en' => 'Najaf',             'name_ar' => 'النجف',            'latitude' => 31.9966,  'longitude' => 44.3351],
                ['name_en' => 'Sulaymaniyah',      'name_ar' => 'السليمانية',       'latitude' => 35.5573,  'longitude' => 45.4329],
            ],
            'SY' => [
                ['name_en' => 'Damascus',          'name_ar' => 'دمشق',             'latitude' => 33.5138,  'longitude' => 36.2765],
                ['name_en' => 'Aleppo',            'name_ar' => 'حلب',              'latitude' => 36.2021,  'longitude' => 37.1343],
                ['name_en' => 'Latakia',           'name_ar' => 'اللاذقية',         'latitude' => 35.5318,  'longitude' => 35.7915],
            ],
            'YE' => [
                ['name_en' => 'Sana\'a',           'name_ar' => 'صنعاء',            'latitude' => 15.3694,  'longitude' => 44.1910],
                ['name_en' => 'Aden',              'name_ar' => 'عدن',              'latitude' => 12.7797,  'longitude' => 45.0360],
                ['name_en' => 'Socotra',           'name_ar' => 'سقطرى',            'latitude' => 12.4634,  'longitude' => 53.8237],
            ],
            'LY' => [
                ['name_en' => 'Tripoli',           'name_ar' => 'طرابلس',           'latitude' => 32.9028,  'longitude' => 13.1800],
                ['name_en' => 'Benghazi',          'name_ar' => 'بنغازي',           'latitude' => 32.1167,  'longitude' => 20.0667],
            ],
            'SD' => [
                ['name_en' => 'Khartoum',          'name_ar' => 'الخرطوم',          'latitude' => 15.5007,  'longitude' => 32.5599],
                ['name_en' => 'Omdurman',          'name_ar' => 'أم درمان',         'latitude' => 15.6445,  'longitude' => 32.4773],
            ],
            'DZ' => [
                ['name_en' => 'Algiers',           'name_ar' => 'الجزائر',          'latitude' => 36.7372,  'longitude' => 3.0865],
                ['name_en' => 'Oran',              'name_ar' => 'وهران',            'latitude' => 35.6969,  'longitude' => -0.6331],
                ['name_en' => 'Constantine',       'name_ar' => 'قسنطينة',          'latitude' => 36.3650,  'longitude' => 6.6147],
            ],
            'PS' => [
                ['name_en' => 'Ramallah',          'name_ar' => 'رام الله',         'latitude' => 31.8996,  'longitude' => 35.2042],
                ['name_en' => 'Gaza',              'name_ar' => 'غزة',              'latitude' => 31.5017,  'longitude' => 34.4668],
                ['name_en' => 'Bethlehem',         'name_ar' => 'بيت لحم',          'latitude' => 31.7054,  'longitude' => 35.2024],
            ],

            // ── Rest of Asia ─────────────────────────────────────────────────

            'TR' => [
                ['name_en' => 'Istanbul',          'name_ar' => 'إسطنبول',          'latitude' => 41.0082,  'longitude' => 28.9784],
                ['name_en' => 'Ankara',            'name_ar' => 'أنقرة',            'latitude' => 39.9334,  'longitude' => 32.8597],
                ['name_en' => 'Antalya',           'name_ar' => 'أنطاليا',          'latitude' => 36.8841,  'longitude' => 30.7056],
                ['name_en' => 'Cappadocia',        'name_ar' => 'كابادوكيا',        'latitude' => 38.6431,  'longitude' => 34.8284],
                ['name_en' => 'Bodrum',            'name_ar' => 'بودروم',           'latitude' => 37.0344,  'longitude' => 27.4305],
                ['name_en' => 'Izmir',             'name_ar' => 'إزمير',            'latitude' => 38.4237,  'longitude' => 27.1428],
                ['name_en' => 'Trabzon',           'name_ar' => 'طرابزون',          'latitude' => 41.0027,  'longitude' => 39.7168],
            ],
            'TH' => [
                ['name_en' => 'Bangkok',           'name_ar' => 'بانكوك',           'latitude' => 13.7563,  'longitude' => 100.5018],
                ['name_en' => 'Phuket',            'name_ar' => 'فوكيت',            'latitude' => 7.8804,   'longitude' => 98.3923],
                ['name_en' => 'Chiang Mai',        'name_ar' => 'شيانغ ماي',        'latitude' => 18.7884,  'longitude' => 98.9853],
                ['name_en' => 'Pattaya',           'name_ar' => 'باتايا',           'latitude' => 12.9236,  'longitude' => 100.8825],
                ['name_en' => 'Krabi',             'name_ar' => 'كرابي',            'latitude' => 8.0863,   'longitude' => 98.9063],
            ],
            'ID' => [
                ['name_en' => 'Bali',              'name_ar' => 'بالي',             'latitude' => -8.3405,  'longitude' => 115.0920],
                ['name_en' => 'Jakarta',           'name_ar' => 'جاكرتا',           'latitude' => -6.2088,  'longitude' => 106.8456],
                ['name_en' => 'Yogyakarta',        'name_ar' => 'يوغياكارتا',       'latitude' => -7.7956,  'longitude' => 110.3695],
                ['name_en' => 'Lombok',            'name_ar' => 'لومبوك',           'latitude' => -8.6500,  'longitude' => 116.3244],
                ['name_en' => 'Komodo',            'name_ar' => 'كومودو',           'latitude' => -8.5500,  'longitude' => 119.4833],
            ],
            'MV' => [
                ['name_en' => 'Malé',              'name_ar' => 'ماليه',            'latitude' => 4.1755,   'longitude' => 73.5093],
                ['name_en' => 'Addu Atoll',        'name_ar' => 'أتول أدو',         'latitude' => -0.6300,  'longitude' => 73.1000],
            ],
            'JP' => [
                ['name_en' => 'Tokyo',             'name_ar' => 'طوكيو',            'latitude' => 35.6762,  'longitude' => 139.6503],
                ['name_en' => 'Osaka',             'name_ar' => 'أوساكا',           'latitude' => 34.6937,  'longitude' => 135.5023],
                ['name_en' => 'Kyoto',             'name_ar' => 'كيوتو',            'latitude' => 35.0116,  'longitude' => 135.7681],
                ['name_en' => 'Hiroshima',         'name_ar' => 'هيروشيما',         'latitude' => 34.3853,  'longitude' => 132.4553],
                ['name_en' => 'Sapporo',           'name_ar' => 'سابورو',           'latitude' => 43.0618,  'longitude' => 141.3545],
            ],
            'SG' => [
                ['name_en' => 'Singapore',         'name_ar' => 'سنغافورة',         'latitude' => 1.3521,   'longitude' => 103.8198],
            ],
            'MY' => [
                ['name_en' => 'Kuala Lumpur',      'name_ar' => 'كوالالمبور',       'latitude' => 3.1390,   'longitude' => 101.6869],
                ['name_en' => 'Penang',            'name_ar' => 'بينانغ',           'latitude' => 5.4141,   'longitude' => 100.3288],
                ['name_en' => 'Langkawi',          'name_ar' => 'لنكاوي',           'latitude' => 6.3500,   'longitude' => 99.8000],
                ['name_en' => 'Kota Kinabalu',     'name_ar' => 'كوتا كينابالو',    'latitude' => 5.9804,   'longitude' => 116.0735],
            ],
            'IN' => [
                ['name_en' => 'Mumbai',            'name_ar' => 'مومباي',           'latitude' => 19.0760,  'longitude' => 72.8777],
                ['name_en' => 'Delhi',             'name_ar' => 'دلهي',             'latitude' => 28.6139,  'longitude' => 77.2090],
                ['name_en' => 'Goa',               'name_ar' => 'غوا',              'latitude' => 15.2993,  'longitude' => 74.1240],
                ['name_en' => 'Jaipur',            'name_ar' => 'جايبور',           'latitude' => 26.9124,  'longitude' => 75.7873],
                ['name_en' => 'Agra',              'name_ar' => 'أغرا',             'latitude' => 27.1767,  'longitude' => 78.0081],
                ['name_en' => 'Kerala',            'name_ar' => 'كيرالا',           'latitude' => 10.8505,  'longitude' => 76.2711],
                ['name_en' => 'Bangalore',         'name_ar' => 'بنغالور',          'latitude' => 12.9716,  'longitude' => 77.5946],
            ],
            'CN' => [
                ['name_en' => 'Beijing',           'name_ar' => 'بكين',             'latitude' => 39.9042,  'longitude' => 116.4074],
                ['name_en' => 'Shanghai',          'name_ar' => 'شانغهاي',          'latitude' => 31.2304,  'longitude' => 121.4737],
                ['name_en' => 'Guangzhou',         'name_ar' => 'غوانغتشو',         'latitude' => 23.1291,  'longitude' => 113.2644],
                ['name_en' => 'Shenzhen',          'name_ar' => 'شنجن',             'latitude' => 22.5431,  'longitude' => 114.0579],
                ['name_en' => 'Xi\'an',            'name_ar' => 'شيان',             'latitude' => 34.3416,  'longitude' => 108.9398],
                ['name_en' => 'Chengdu',           'name_ar' => 'تشنغدو',           'latitude' => 30.5728,  'longitude' => 104.0668],
            ],
            'KR' => [
                ['name_en' => 'Seoul',             'name_ar' => 'سيول',             'latitude' => 37.5665,  'longitude' => 126.9780],
                ['name_en' => 'Busan',             'name_ar' => 'بوسان',            'latitude' => 35.1796,  'longitude' => 129.0756],
                ['name_en' => 'Jeju',              'name_ar' => 'جيجو',             'latitude' => 33.4996,  'longitude' => 126.5312],
            ],
            'VN' => [
                ['name_en' => 'Ho Chi Minh City',  'name_ar' => 'مدينة هو تشي منه', 'latitude' => 10.8231,  'longitude' => 106.6297],
                ['name_en' => 'Hanoi',             'name_ar' => 'هانوي',            'latitude' => 21.0278,  'longitude' => 105.8342],
                ['name_en' => 'Da Nang',           'name_ar' => 'دا نانغ',          'latitude' => 16.0544,  'longitude' => 108.2022],
                ['name_en' => 'Hoi An',            'name_ar' => 'هوي آن',           'latitude' => 15.8801,  'longitude' => 108.3380],
                ['name_en' => 'Halong Bay',        'name_ar' => 'خليج هالونغ',      'latitude' => 20.9101,  'longitude' => 107.1839],
            ],
            'AZ' => [
                ['name_en' => 'Baku',              'name_ar' => 'باكو',             'latitude' => 40.4093,  'longitude' => 49.8671],
                ['name_en' => 'Gabala',            'name_ar' => 'قبالا',            'latitude' => 40.9982,  'longitude' => 47.8725],
                ['name_en' => 'Sheki',             'name_ar' => 'شكي',              'latitude' => 41.1944,  'longitude' => 47.1707],
            ],
            'GE' => [
                ['name_en' => 'Tbilisi',           'name_ar' => 'تبليسي',           'latitude' => 41.6938,  'longitude' => 44.8015],
                ['name_en' => 'Batumi',            'name_ar' => 'باتومي',           'latitude' => 41.6411,  'longitude' => 41.6406],
                ['name_en' => 'Kazbegi',           'name_ar' => 'كازبيغي',          'latitude' => 42.6560,  'longitude' => 44.6371],
            ],
            'AM' => [
                ['name_en' => 'Yerevan',           'name_ar' => 'يريفان',           'latitude' => 40.1872,  'longitude' => 44.5152],
                ['name_en' => 'Gyumri',            'name_ar' => 'غيومري',           'latitude' => 40.7942,  'longitude' => 43.8453],
            ],
            'KZ' => [
                ['name_en' => 'Almaty',            'name_ar' => 'ألماتي',           'latitude' => 43.2220,  'longitude' => 76.8512],
                ['name_en' => 'Astana',            'name_ar' => 'أستانا',           'latitude' => 51.1801,  'longitude' => 71.4460],
            ],
            'UZ' => [
                ['name_en' => 'Tashkent',          'name_ar' => 'طشقند',            'latitude' => 41.2995,  'longitude' => 69.2401],
                ['name_en' => 'Samarkand',         'name_ar' => 'سمرقند',           'latitude' => 39.6270,  'longitude' => 66.9749],
                ['name_en' => 'Bukhara',           'name_ar' => 'بخارى',            'latitude' => 39.7747,  'longitude' => 64.4286],
            ],
            'PK' => [
                ['name_en' => 'Karachi',           'name_ar' => 'كراتشي',           'latitude' => 24.8607,  'longitude' => 67.0011],
                ['name_en' => 'Lahore',            'name_ar' => 'لاهور',            'latitude' => 31.5204,  'longitude' => 74.3587],
                ['name_en' => 'Islamabad',         'name_ar' => 'إسلام آباد',       'latitude' => 33.6844,  'longitude' => 73.0479],
                ['name_en' => 'Hunza Valley',      'name_ar' => 'وادي هنزة',        'latitude' => 36.3162,  'longitude' => 74.6498],
            ],
            'LK' => [
                ['name_en' => 'Colombo',           'name_ar' => 'كولومبو',          'latitude' => 6.9271,   'longitude' => 79.8612],
                ['name_en' => 'Kandy',             'name_ar' => 'كاندي',            'latitude' => 7.2906,   'longitude' => 80.6337],
                ['name_en' => 'Sigiriya',          'name_ar' => 'سيغيريا',          'latitude' => 7.9570,   'longitude' => 80.7603],
            ],
            'NP' => [
                ['name_en' => 'Kathmandu',         'name_ar' => 'كاتماندو',         'latitude' => 27.7172,  'longitude' => 85.3240],
                ['name_en' => 'Pokhara',           'name_ar' => 'بوخارا',           'latitude' => 28.2096,  'longitude' => 83.9856],
            ],
            'PH' => [
                ['name_en' => 'Manila',            'name_ar' => 'مانيلا',           'latitude' => 14.5995,  'longitude' => 120.9842],
                ['name_en' => 'Cebu',              'name_ar' => 'سيبو',             'latitude' => 10.3157,  'longitude' => 123.8854],
                ['name_en' => 'Palawan',           'name_ar' => 'بالاوان',          'latitude' => 9.5000,   'longitude' => 118.7339],
            ],
            'KH' => [
                ['name_en' => 'Phnom Penh',        'name_ar' => 'بنوم بنه',         'latitude' => 11.5564,  'longitude' => 104.9282],
                ['name_en' => 'Siem Reap',         'name_ar' => 'سيم ريب',          'latitude' => 13.3671,  'longitude' => 103.8448],
            ],
            'MM' => [
                ['name_en' => 'Yangon',            'name_ar' => 'يانغون',           'latitude' => 16.8661,  'longitude' => 96.1951],
                ['name_en' => 'Bagan',             'name_ar' => 'باغان',            'latitude' => 21.1717,  'longitude' => 94.8585],
            ],
            'IL' => [
                ['name_en' => 'Jerusalem',         'name_ar' => 'القدس',            'latitude' => 31.7683,  'longitude' => 35.2137],
                ['name_en' => 'Tel Aviv',          'name_ar' => 'تل أبيب',          'latitude' => 32.0853,  'longitude' => 34.7818],
                ['name_en' => 'Eilat',             'name_ar' => 'إيلات',            'latitude' => 29.5577,  'longitude' => 34.9519],
            ],
            'IR' => [
                ['name_en' => 'Tehran',            'name_ar' => 'طهران',            'latitude' => 35.6892,  'longitude' => 51.3890],
                ['name_en' => 'Isfahan',           'name_ar' => 'أصفهان',           'latitude' => 32.6539,  'longitude' => 51.6660],
                ['name_en' => 'Shiraz',            'name_ar' => 'شيراز',            'latitude' => 29.5918,  'longitude' => 52.5837],
                ['name_en' => 'Mashhad',           'name_ar' => 'مشهد',             'latitude' => 36.2972,  'longitude' => 59.6067],
            ],
            'CY' => [
                ['name_en' => 'Nicosia',           'name_ar' => 'نيقوسيا',          'latitude' => 35.1856,  'longitude' => 33.3823],
                ['name_en' => 'Limassol',          'name_ar' => 'ليماسول',          'latitude' => 34.6786,  'longitude' => 33.0413],
                ['name_en' => 'Paphos',            'name_ar' => 'بافوس',            'latitude' => 34.7751,  'longitude' => 32.4240],
            ],
            'BD' => [
                ['name_en' => 'Dhaka',             'name_ar' => 'دكا',              'latitude' => 23.8103,  'longitude' => 90.4125],
                ['name_en' => "Cox's Bazar",       'name_ar' => 'كوكس بازار',       'latitude' => 21.4272,  'longitude' => 91.9997],
            ],

            // ── Europe ────────────────────────────────────────────────────────

            'FR' => [
                ['name_en' => 'Paris',             'name_ar' => 'باريس',            'latitude' => 48.8566,  'longitude' => 2.3522],
                ['name_en' => 'Nice',              'name_ar' => 'نيس',              'latitude' => 43.7102,  'longitude' => 7.2620],
                ['name_en' => 'Lyon',              'name_ar' => 'ليون',             'latitude' => 45.7640,  'longitude' => 4.8357],
                ['name_en' => 'Marseille',         'name_ar' => 'مرسيليا',          'latitude' => 43.2965,  'longitude' => 5.3698],
                ['name_en' => 'Bordeaux',          'name_ar' => 'بوردو',            'latitude' => 44.8378,  'longitude' => -0.5792],
            ],
            'IT' => [
                ['name_en' => 'Rome',              'name_ar' => 'روما',             'latitude' => 41.9028,  'longitude' => 12.4964],
                ['name_en' => 'Milan',             'name_ar' => 'ميلان',            'latitude' => 45.4654,  'longitude' => 9.1859],
                ['name_en' => 'Venice',            'name_ar' => 'البندقية',         'latitude' => 45.4408,  'longitude' => 12.3155],
                ['name_en' => 'Florence',          'name_ar' => 'فلورنسا',          'latitude' => 43.7696,  'longitude' => 11.2558],
                ['name_en' => 'Amalfi Coast',      'name_ar' => 'ساحل أمالفي',      'latitude' => 40.6342,  'longitude' => 14.6027],
                ['name_en' => 'Sicily',            'name_ar' => 'صقلية',            'latitude' => 37.5990,  'longitude' => 14.0154],
            ],
            'ES' => [
                ['name_en' => 'Madrid',            'name_ar' => 'مدريد',            'latitude' => 40.4168,  'longitude' => -3.7038],
                ['name_en' => 'Barcelona',         'name_ar' => 'برشلونة',          'latitude' => 41.3851,  'longitude' => 2.1734],
                ['name_en' => 'Seville',           'name_ar' => 'إشبيلية',          'latitude' => 37.3891,  'longitude' => -5.9845],
                ['name_en' => 'Granada',           'name_ar' => 'غرناطة',           'latitude' => 37.1773,  'longitude' => -3.5986],
                ['name_en' => 'Ibiza',             'name_ar' => 'إيبيزا',           'latitude' => 38.9067,  'longitude' => 1.4204],
            ],
            'GB' => [
                ['name_en' => 'London',            'name_ar' => 'لندن',             'latitude' => 51.5074,  'longitude' => -0.1278],
                ['name_en' => 'Edinburgh',         'name_ar' => 'إدنبرة',           'latitude' => 55.9533,  'longitude' => -3.1883],
                ['name_en' => 'Manchester',        'name_ar' => 'مانشستر',          'latitude' => 53.4808,  'longitude' => -2.2426],
                ['name_en' => 'Birmingham',        'name_ar' => 'برمنغهام',         'latitude' => 52.4862,  'longitude' => -1.8904],
            ],
            'DE' => [
                ['name_en' => 'Berlin',            'name_ar' => 'برلين',            'latitude' => 52.5200,  'longitude' => 13.4050],
                ['name_en' => 'Munich',            'name_ar' => 'ميونخ',            'latitude' => 48.1351,  'longitude' => 11.5820],
                ['name_en' => 'Frankfurt',         'name_ar' => 'فرانكفورت',        'latitude' => 50.1109,  'longitude' => 8.6821],
                ['name_en' => 'Hamburg',           'name_ar' => 'هامبورغ',          'latitude' => 53.5753,  'longitude' => 10.0153],
            ],
            'GR' => [
                ['name_en' => 'Athens',            'name_ar' => 'أثينا',            'latitude' => 37.9838,  'longitude' => 23.7275],
                ['name_en' => 'Santorini',         'name_ar' => 'سانتوريني',        'latitude' => 36.3932,  'longitude' => 25.4615],
                ['name_en' => 'Mykonos',           'name_ar' => 'ميكونوس',          'latitude' => 37.4415,  'longitude' => 25.3281],
                ['name_en' => 'Rhodes',            'name_ar' => 'رودس',             'latitude' => 36.4340,  'longitude' => 28.2176],
                ['name_en' => 'Crete',             'name_ar' => 'كريت',             'latitude' => 35.2401,  'longitude' => 24.8093],
            ],
            'PT' => [
                ['name_en' => 'Lisbon',            'name_ar' => 'لشبونة',           'latitude' => 38.7223,  'longitude' => -9.1393],
                ['name_en' => 'Porto',             'name_ar' => 'بورتو',            'latitude' => 41.1579,  'longitude' => -8.6291],
                ['name_en' => 'Algarve',           'name_ar' => 'الغارف',           'latitude' => 37.0179,  'longitude' => -8.1330],
            ],
            'NL' => [
                ['name_en' => 'Amsterdam',         'name_ar' => 'أمستردام',         'latitude' => 52.3676,  'longitude' => 4.9041],
                ['name_en' => 'Rotterdam',         'name_ar' => 'روتردام',          'latitude' => 51.9225,  'longitude' => 4.4792],
                ['name_en' => 'The Hague',         'name_ar' => 'لاهاي',            'latitude' => 52.0705,  'longitude' => 4.3007],
            ],
            'CH' => [
                ['name_en' => 'Zurich',            'name_ar' => 'زيورخ',            'latitude' => 47.3769,  'longitude' => 8.5417],
                ['name_en' => 'Geneva',            'name_ar' => 'جنيف',             'latitude' => 46.2044,  'longitude' => 6.1432],
                ['name_en' => 'Interlaken',        'name_ar' => 'إنترلاكن',         'latitude' => 46.6863,  'longitude' => 7.8632],
                ['name_en' => 'Zermatt',           'name_ar' => 'زيرمات',           'latitude' => 46.0207,  'longitude' => 7.7491],
            ],
            'AT' => [
                ['name_en' => 'Vienna',            'name_ar' => 'فيينا',            'latitude' => 48.2082,  'longitude' => 16.3738],
                ['name_en' => 'Salzburg',          'name_ar' => 'سالزبورغ',         'latitude' => 47.8095,  'longitude' => 13.0550],
                ['name_en' => 'Innsbruck',         'name_ar' => 'إنسبروك',          'latitude' => 47.2692,  'longitude' => 11.4041],
            ],
            'CZ' => [
                ['name_en' => 'Prague',            'name_ar' => 'براغ',             'latitude' => 50.0755,  'longitude' => 14.4378],
                ['name_en' => 'Brno',              'name_ar' => 'برنو',             'latitude' => 49.1951,  'longitude' => 16.6068],
            ],
            'HR' => [
                ['name_en' => 'Dubrovnik',         'name_ar' => 'دوبروفنيك',        'latitude' => 42.6507,  'longitude' => 18.0944],
                ['name_en' => 'Split',             'name_ar' => 'سبليت',            'latitude' => 43.5081,  'longitude' => 16.4402],
                ['name_en' => 'Zagreb',            'name_ar' => 'زغرب',             'latitude' => 45.8150,  'longitude' => 15.9819],
            ],
            'PL' => [
                ['name_en' => 'Warsaw',            'name_ar' => 'وارسو',            'latitude' => 52.2297,  'longitude' => 21.0122],
                ['name_en' => 'Krakow',            'name_ar' => 'كراكوف',           'latitude' => 50.0647,  'longitude' => 19.9450],
                ['name_en' => 'Gdansk',            'name_ar' => 'غدانسك',           'latitude' => 54.3520,  'longitude' => 18.6466],
            ],
            'HU' => [
                ['name_en' => 'Budapest',          'name_ar' => 'بودابست',          'latitude' => 47.4979,  'longitude' => 19.0402],
                ['name_en' => 'Lake Balaton',      'name_ar' => 'بحيرة بالاتون',    'latitude' => 46.8367,  'longitude' => 17.7363],
            ],
            'RU' => [
                ['name_en' => 'Moscow',            'name_ar' => 'موسكو',            'latitude' => 55.7558,  'longitude' => 37.6173],
                ['name_en' => 'Saint Petersburg',  'name_ar' => 'سانت بطرسبرغ',    'latitude' => 59.9311,  'longitude' => 30.3609],
                ['name_en' => 'Sochi',             'name_ar' => 'سوتشي',            'latitude' => 43.6028,  'longitude' => 39.7342],
                ['name_en' => 'Kazan',             'name_ar' => 'قازان',            'latitude' => 55.8304,  'longitude' => 49.0661],
            ],
            'UA' => [
                ['name_en' => 'Kyiv',              'name_ar' => 'كييف',             'latitude' => 50.4501,  'longitude' => 30.5234],
                ['name_en' => 'Lviv',              'name_ar' => 'لفيف',             'latitude' => 49.8397,  'longitude' => 24.0297],
            ],
            'RS' => [
                ['name_en' => 'Belgrade',          'name_ar' => 'بلغراد',           'latitude' => 44.7866,  'longitude' => 20.4489],
            ],
            'BA' => [
                ['name_en' => 'Sarajevo',          'name_ar' => 'سراييفو',          'latitude' => 43.8563,  'longitude' => 18.4131],
                ['name_en' => 'Mostar',            'name_ar' => 'موستار',           'latitude' => 43.3438,  'longitude' => 17.8078],
            ],
            'AL' => [
                ['name_en' => 'Tirana',            'name_ar' => 'تيرانا',           'latitude' => 41.3275,  'longitude' => 19.8187],
            ],
            'ME' => [
                ['name_en' => 'Podgorica',         'name_ar' => 'بودغوريتسا',       'latitude' => 42.4304,  'longitude' => 19.2594],
                ['name_en' => 'Kotor',             'name_ar' => 'كوتور',            'latitude' => 42.4247,  'longitude' => 18.7712],
            ],
            'RO' => [
                ['name_en' => 'Bucharest',         'name_ar' => 'بوخارست',          'latitude' => 44.4268,  'longitude' => 26.1025],
                ['name_en' => 'Brasov',            'name_ar' => 'براشوف',           'latitude' => 45.6580,  'longitude' => 25.6012],
                ['name_en' => 'Cluj-Napoca',       'name_ar' => 'كلوج نابوكا',      'latitude' => 46.7712,  'longitude' => 23.6236],
            ],
            'BG' => [
                ['name_en' => 'Sofia',             'name_ar' => 'صوفيا',            'latitude' => 42.6977,  'longitude' => 23.3219],
                ['name_en' => 'Plovdiv',           'name_ar' => 'بلوفديف',          'latitude' => 42.1354,  'longitude' => 24.7453],
            ],
            'BE' => [
                ['name_en' => 'Brussels',          'name_ar' => 'بروكسل',           'latitude' => 50.8503,  'longitude' => 4.3517],
                ['name_en' => 'Bruges',            'name_ar' => 'بروج',             'latitude' => 51.2093,  'longitude' => 3.2247],
                ['name_en' => 'Ghent',             'name_ar' => 'غنت',              'latitude' => 51.0543,  'longitude' => 3.7174],
            ],
            'DK' => [
                ['name_en' => 'Copenhagen',        'name_ar' => 'كوبنهاغن',         'latitude' => 55.6761,  'longitude' => 12.5683],
            ],
            'SE' => [
                ['name_en' => 'Stockholm',         'name_ar' => 'ستوكهولم',         'latitude' => 59.3293,  'longitude' => 18.0686],
                ['name_en' => 'Gothenburg',        'name_ar' => 'غوتنبرغ',          'latitude' => 57.7089,  'longitude' => 11.9746],
            ],
            'NO' => [
                ['name_en' => 'Oslo',              'name_ar' => 'أوسلو',            'latitude' => 59.9139,  'longitude' => 10.7522],
                ['name_en' => 'Bergen',            'name_ar' => 'بيرغن',            'latitude' => 60.3913,  'longitude' => 5.3221],
            ],
            'FI' => [
                ['name_en' => 'Helsinki',          'name_ar' => 'هلسنكي',           'latitude' => 60.1699,  'longitude' => 24.9384],
            ],
            'IS' => [
                ['name_en' => 'Reykjavik',         'name_ar' => 'ريكيافيك',         'latitude' => 64.1466,  'longitude' => -21.9426],
            ],
            'IE' => [
                ['name_en' => 'Dublin',            'name_ar' => 'دبلن',             'latitude' => 53.3498,  'longitude' => -6.2603],
            ],
            'MT' => [
                ['name_en' => 'Valletta',          'name_ar' => 'فاليتا',           'latitude' => 35.8997,  'longitude' => 14.5147],
            ],

            // ── Africa (tourist focus) ─────────────────────────────────────────

            'KE' => [
                ['name_en' => 'Nairobi',           'name_ar' => 'نيروبي',           'latitude' => -1.2921,  'longitude' => 36.8219],
                ['name_en' => 'Mombasa',           'name_ar' => 'مومباسا',          'latitude' => -4.0435,  'longitude' => 39.6682],
                ['name_en' => 'Maasai Mara',       'name_ar' => 'ماساي مارا',       'latitude' => -1.5167,  'longitude' => 35.1333],
                ['name_en' => 'Diani Beach',       'name_ar' => 'شاطئ دياني',       'latitude' => -4.3167,  'longitude' => 39.5833],
            ],
            'TZ' => [
                ['name_en' => 'Dar es Salaam',     'name_ar' => 'دار السلام',       'latitude' => -6.7924,  'longitude' => 39.2083],
                ['name_en' => 'Zanzibar',          'name_ar' => 'زنجبار',           'latitude' => -6.1630,  'longitude' => 39.1990],
                ['name_en' => 'Serengeti',         'name_ar' => 'سيرنغيتي',         'latitude' => -2.1540,  'longitude' => 34.6857],
                ['name_en' => 'Arusha',            'name_ar' => 'أروشا',            'latitude' => -3.3869,  'longitude' => 36.6830],
            ],
            'ZA' => [
                ['name_en' => 'Cape Town',         'name_ar' => 'كيب تاون',         'latitude' => -33.9249, 'longitude' => 18.4241],
                ['name_en' => 'Johannesburg',      'name_ar' => 'جوهانسبرغ',        'latitude' => -26.2041, 'longitude' => 28.0473],
                ['name_en' => 'Durban',            'name_ar' => 'ديربان',           'latitude' => -29.8587, 'longitude' => 31.0218],
                ['name_en' => 'Kruger National Park','name_ar' => 'حديقة كروغر',    'latitude' => -24.0000, 'longitude' => 31.5000],
            ],
            'MU' => [
                ['name_en' => 'Port Louis',        'name_ar' => 'بورت لويس',        'latitude' => -20.1609, 'longitude' => 57.4982],
                ['name_en' => 'Grand Baie',        'name_ar' => 'غراند باي',        'latitude' => -20.0130, 'longitude' => 57.5800],
            ],
            'SC' => [
                ['name_en' => 'Victoria',          'name_ar' => 'فيكتوريا',         'latitude' => -4.6191,  'longitude' => 55.4513],
                ['name_en' => 'Praslin',           'name_ar' => 'براسلان',          'latitude' => -4.3228,  'longitude' => 55.7432],
            ],
            'ET' => [
                ['name_en' => 'Addis Ababa',       'name_ar' => 'أديس أبابا',       'latitude' => 9.0320,   'longitude' => 38.7469],
            ],
            'GH' => [
                ['name_en' => 'Accra',             'name_ar' => 'أكرا',             'latitude' => 5.6037,   'longitude' => -0.1870],
            ],
            'NG' => [
                ['name_en' => 'Lagos',             'name_ar' => 'لاغوس',            'latitude' => 6.5244,   'longitude' => 3.3792],
                ['name_en' => 'Abuja',             'name_ar' => 'أبوجا',            'latitude' => 9.0765,   'longitude' => 7.3986],
            ],
            'RW' => [
                ['name_en' => 'Kigali',            'name_ar' => 'كيغالي',           'latitude' => -1.9441,  'longitude' => 30.0619],
            ],

            // ── Americas ──────────────────────────────────────────────────────

            'US' => [
                ['name_en' => 'New York',          'name_ar' => 'نيويورك',          'latitude' => 40.7128,  'longitude' => -74.0060],
                ['name_en' => 'Los Angeles',       'name_ar' => 'لوس أنجلوس',       'latitude' => 34.0522,  'longitude' => -118.2437],
                ['name_en' => 'Las Vegas',         'name_ar' => 'لاس فيغاس',        'latitude' => 36.1699,  'longitude' => -115.1398],
                ['name_en' => 'Miami',             'name_ar' => 'ميامي',            'latitude' => 25.7617,  'longitude' => -80.1918],
                ['name_en' => 'Orlando',           'name_ar' => 'أورلاندو',         'latitude' => 28.5383,  'longitude' => -81.3792],
                ['name_en' => 'Chicago',           'name_ar' => 'شيكاغو',           'latitude' => 41.8781,  'longitude' => -87.6298],
                ['name_en' => 'San Francisco',     'name_ar' => 'سان فرانسيسكو',    'latitude' => 37.7749,  'longitude' => -122.4194],
                ['name_en' => 'Washington D.C.',   'name_ar' => 'واشنطن',           'latitude' => 38.9072,  'longitude' => -77.0369],
            ],
            'CA' => [
                ['name_en' => 'Toronto',           'name_ar' => 'تورنتو',           'latitude' => 43.6532,  'longitude' => -79.3832],
                ['name_en' => 'Vancouver',         'name_ar' => 'فانكوفر',          'latitude' => 49.2827,  'longitude' => -123.1207],
                ['name_en' => 'Montreal',          'name_ar' => 'مونتريال',         'latitude' => 45.5017,  'longitude' => -73.5673],
                ['name_en' => 'Quebec City',       'name_ar' => 'مدينة كيبيك',      'latitude' => 46.8139,  'longitude' => -71.2080],
                ['name_en' => 'Banff',             'name_ar' => 'بانف',             'latitude' => 51.1784,  'longitude' => -115.5708],
            ],
            'MX' => [
                ['name_en' => 'Mexico City',       'name_ar' => 'مكسيكو سيتي',      'latitude' => 19.4326,  'longitude' => -99.1332],
                ['name_en' => 'Cancún',            'name_ar' => 'كانكون',           'latitude' => 21.1619,  'longitude' => -86.8515],
                ['name_en' => 'Playa del Carmen',  'name_ar' => 'بلايا ديل كارمن',  'latitude' => 20.6296,  'longitude' => -87.0739],
                ['name_en' => 'Los Cabos',         'name_ar' => 'لوس كابوس',        'latitude' => 22.8897,  'longitude' => -109.9167],
            ],
            'BR' => [
                ['name_en' => 'Rio de Janeiro',    'name_ar' => 'ريو دي جانيرو',    'latitude' => -22.9068, 'longitude' => -43.1729],
                ['name_en' => 'São Paulo',         'name_ar' => 'ساو باولو',        'latitude' => -23.5505, 'longitude' => -46.6333],
                ['name_en' => 'Salvador',          'name_ar' => 'سالفادور',         'latitude' => -12.9714, 'longitude' => -38.5014],
                ['name_en' => 'Manaus',            'name_ar' => 'ماناوس',           'latitude' => -3.1190,  'longitude' => -60.0217],
            ],
            'AR' => [
                ['name_en' => 'Buenos Aires',      'name_ar' => 'بوينس آيرس',       'latitude' => -34.6037, 'longitude' => -58.3816],
                ['name_en' => 'Patagonia',         'name_ar' => 'باتاغونيا',        'latitude' => -40.0000, 'longitude' => -71.0000],
                ['name_en' => 'Mendoza',           'name_ar' => 'ميندوزا',          'latitude' => -32.8908, 'longitude' => -68.8272],
            ],
            'PE' => [
                ['name_en' => 'Lima',              'name_ar' => 'ليما',             'latitude' => -12.0464, 'longitude' => -77.0428],
                ['name_en' => 'Machu Picchu',      'name_ar' => 'ماتشو بيتشو',      'latitude' => -13.1631, 'longitude' => -72.5450],
                ['name_en' => 'Cusco',             'name_ar' => 'كوسكو',            'latitude' => -13.5319, 'longitude' => -71.9675],
            ],
            'CO' => [
                ['name_en' => 'Bogotá',            'name_ar' => 'بوغوتا',           'latitude' => 4.7110,   'longitude' => -74.0721],
                ['name_en' => 'Cartagena',         'name_ar' => 'قرطاجنة',          'latitude' => 10.3910,  'longitude' => -75.4794],
                ['name_en' => 'Medellín',          'name_ar' => 'ميديين',           'latitude' => 6.2442,   'longitude' => -75.5812],
            ],
            'CL' => [
                ['name_en' => 'Santiago',          'name_ar' => 'سانتياغو',         'latitude' => -33.4489, 'longitude' => -70.6693],
                ['name_en' => 'Atacama Desert',    'name_ar' => 'صحراء أتاكاما',    'latitude' => -23.6509, 'longitude' => -69.0029],
            ],
            'CU' => [
                ['name_en' => 'Havana',            'name_ar' => 'هافانا',           'latitude' => 23.1136,  'longitude' => -82.3666],
                ['name_en' => 'Varadero',          'name_ar' => 'فاراديرو',         'latitude' => 23.1535,  'longitude' => -81.2505],
            ],
            'JM' => [
                ['name_en' => 'Kingston',          'name_ar' => 'كينغستون',         'latitude' => 17.9714,  'longitude' => -76.7936],
                ['name_en' => 'Montego Bay',       'name_ar' => 'مونتيغو باي',      'latitude' => 18.4762,  'longitude' => -77.8939],
            ],
            'DO' => [
                ['name_en' => 'Santo Domingo',     'name_ar' => 'سانتو دومينغو',    'latitude' => 18.4861,  'longitude' => -69.9312],
                ['name_en' => 'Punta Cana',        'name_ar' => 'بونتا كانا',       'latitude' => 18.5601,  'longitude' => -68.3725],
            ],
            'BB' => [
                ['name_en' => 'Bridgetown',        'name_ar' => 'بريدجتاون',        'latitude' => 13.0967,  'longitude' => -59.6145],
            ],

            // ── Oceania ───────────────────────────────────────────────────────

            'AU' => [
                ['name_en' => 'Sydney',            'name_ar' => 'سيدني',            'latitude' => -33.8688, 'longitude' => 151.2093],
                ['name_en' => 'Melbourne',         'name_ar' => 'ملبورن',           'latitude' => -37.8136, 'longitude' => 144.9631],
                ['name_en' => 'Gold Coast',        'name_ar' => 'الساحل الذهبي',    'latitude' => -28.0167, 'longitude' => 153.4000],
                ['name_en' => 'Cairns',            'name_ar' => 'كيرنز',            'latitude' => -16.9186, 'longitude' => 145.7781],
                ['name_en' => 'Perth',             'name_ar' => 'بيرث',             'latitude' => -31.9505, 'longitude' => 115.8605],
            ],
            'NZ' => [
                ['name_en' => 'Auckland',          'name_ar' => 'أوكلاند',          'latitude' => -36.8485, 'longitude' => 174.7633],
                ['name_en' => 'Queenstown',        'name_ar' => 'كوينزتاون',        'latitude' => -45.0312, 'longitude' => 168.6626],
                ['name_en' => 'Wellington',        'name_ar' => 'ويلينغتون',        'latitude' => -41.2865, 'longitude' => 174.7762],
            ],
            'FJ' => [
                ['name_en' => 'Suva',              'name_ar' => 'سوفا',             'latitude' => -18.1416, 'longitude' => 178.4419],
                ['name_en' => 'Nadi',              'name_ar' => 'نادي',             'latitude' => -17.7765, 'longitude' => 177.4356],
            ],
        ];
    }
}
