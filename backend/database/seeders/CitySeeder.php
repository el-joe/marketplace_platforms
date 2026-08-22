<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $countryIds = cache('seeder_country_ids') ?? [];

        // Fall back to DB lookup if cache missed
        if (empty($countryIds)) {
            foreach (['SA', 'AE', 'EG', 'KW', 'QA', 'OM', 'BH', 'JO'] as $iso) {
                $row = DB::table('countries')->where('iso_code_2', $iso)->first();
                if ($row)
                    $countryIds[$iso] = $row->id;
            }
        }

        $cities = [
            // Saudi Arabia
            'SA' => [
                ['name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'latitude' => '24.6877', 'longitude' => '46.7219', 'cod_available' => true],
                ['name_en' => 'Jeddah', 'name_ar' => 'جدة', 'latitude' => '21.4858', 'longitude' => '39.1925', 'cod_available' => true],
                ['name_en' => 'Mecca', 'name_ar' => 'مكة المكرمة', 'latitude' => '21.3891', 'longitude' => '39.8579', 'cod_available' => true],
                ['name_en' => 'Medina', 'name_ar' => 'المدينة المنورة', 'latitude' => '24.5247', 'longitude' => '39.5692', 'cod_available' => true],
                ['name_en' => 'Dammam', 'name_ar' => 'الدمام', 'latitude' => '26.4282', 'longitude' => '50.1040', 'cod_available' => true],
                ['name_en' => 'Khobar', 'name_ar' => 'الخبر', 'latitude' => '26.2172', 'longitude' => '50.1971', 'cod_available' => true],
                ['name_en' => 'Tabuk', 'name_ar' => 'تبوك', 'latitude' => '28.3835', 'longitude' => '36.5662', 'cod_available' => false],
                ['name_en' => 'Abha', 'name_ar' => 'أبها', 'latitude' => '18.2164', 'longitude' => '42.5053', 'cod_available' => false],
            ],
            // UAE
            'AE' => [
                ['name_en' => 'Dubai', 'name_ar' => 'دبي', 'latitude' => '25.2048', 'longitude' => '55.2708', 'cod_available' => true],
                ['name_en' => 'Abu Dhabi', 'name_ar' => 'أبو ظبي', 'latitude' => '24.4539', 'longitude' => '54.3773', 'cod_available' => true],
                ['name_en' => 'Sharjah', 'name_ar' => 'الشارقة', 'latitude' => '25.3462', 'longitude' => '55.4209', 'cod_available' => true],
                ['name_en' => 'Ajman', 'name_ar' => 'عجمان', 'latitude' => '25.4052', 'longitude' => '55.5136', 'cod_available' => true],
                ['name_en' => 'Ras Al Khaimah', 'name_ar' => 'رأس الخيمة', 'latitude' => '25.7895', 'longitude' => '55.9432', 'cod_available' => false],
                ['name_en' => 'Fujairah', 'name_ar' => 'الفجيرة', 'latitude' => '25.1288', 'longitude' => '56.3265', 'cod_available' => false],
            ],
            // Egypt
            'EG' => [
                ['name_en' => 'Cairo', 'name_ar' => 'القاهرة', 'latitude' => '30.0444', 'longitude' => '31.2357', 'cod_available' => true],
                ['name_en' => 'Giza', 'name_ar' => 'الجيزة', 'latitude' => '30.0131', 'longitude' => '31.2089', 'cod_available' => true],
                ['name_en' => 'Alexandria', 'name_ar' => 'الإسكندرية', 'latitude' => '31.2001', 'longitude' => '29.9187', 'cod_available' => true],
                ['name_en' => 'Shubra El-Kheima', 'name_ar' => 'شبرا الخيمة', 'latitude' => '30.1286', 'longitude' => '31.2422', 'cod_available' => true],
                ['name_en' => 'Port Said', 'name_ar' => 'بور سعيد', 'latitude' => '31.2565', 'longitude' => '32.2841', 'cod_available' => true],
                ['name_en' => 'Suez', 'name_ar' => 'السويس', 'latitude' => '29.9737', 'longitude' => '32.5311', 'cod_available' => false],
                ['name_en' => 'Luxor', 'name_ar' => 'الأقصر', 'latitude' => '25.6872', 'longitude' => '32.6396', 'cod_available' => false],
                ['name_en' => 'Aswan', 'name_ar' => 'أسوان', 'latitude' => '24.0889', 'longitude' => '32.8998', 'cod_available' => false],
                ['name_en' => 'Mansoura', 'name_ar' => 'المنصورة', 'latitude' => '31.0364', 'longitude' => '31.3807', 'cod_available' => true],
                ['name_en' => 'Tanta', 'name_ar' => 'طنطا', 'latitude' => '30.7865', 'longitude' => '31.0003', 'cod_available' => false],
            ],
            // Kuwait
            'KW' => [
                ['name_en' => 'Kuwait City', 'name_ar' => 'مدينة الكويت', 'latitude' => '29.3759', 'longitude' => '47.9774', 'cod_available' => true],
                ['name_en' => 'Salmiya', 'name_ar' => 'السالمية', 'latitude' => '29.3347', 'longitude' => '48.0794', 'cod_available' => true],
                ['name_en' => 'Hawalli', 'name_ar' => 'حولي', 'latitude' => '29.3369', 'longitude' => '48.0286', 'cod_available' => true],
                ['name_en' => 'Farwaniya', 'name_ar' => 'الفروانية', 'latitude' => '29.2769', 'longitude' => '47.9586', 'cod_available' => true],
                ['name_en' => 'Ahmadi', 'name_ar' => 'الأحمدي', 'latitude' => '29.0769', 'longitude' => '48.0836', 'cod_available' => false],
            ],
            // Qatar
            'QA' => [
                ['name_en' => 'Doha', 'name_ar' => 'الدوحة', 'latitude' => '25.2854', 'longitude' => '51.5310', 'cod_available' => false],
                ['name_en' => 'Al Rayyan', 'name_ar' => 'الريان', 'latitude' => '25.2919', 'longitude' => '51.4245', 'cod_available' => false],
                ['name_en' => 'Al Wakrah', 'name_ar' => 'الوكرة', 'latitude' => '25.1672', 'longitude' => '51.6020', 'cod_available' => false],
            ],
            // Oman
            'OM' => [
                ['name_en' => 'Muscat', 'name_ar' => 'مسقط', 'latitude' => '23.5880', 'longitude' => '58.3829', 'cod_available' => false],
                ['name_en' => 'Salalah', 'name_ar' => 'صلالة', 'latitude' => '17.0151', 'longitude' => '54.0924', 'cod_available' => false],
                ['name_en' => 'Sohar', 'name_ar' => 'صحار', 'latitude' => '24.3473', 'longitude' => '56.7468', 'cod_available' => false],
            ],
            // Bahrain
            'BH' => [
                ['name_en' => 'Manama', 'name_ar' => 'المنامة', 'latitude' => '26.2235', 'longitude' => '50.5876', 'cod_available' => false],
                ['name_en' => 'Riffa', 'name_ar' => 'الرفاع', 'latitude' => '26.1295', 'longitude' => '50.5554', 'cod_available' => false],
                ['name_en' => 'Muharraq', 'name_ar' => 'المحرق', 'latitude' => '26.2694', 'longitude' => '50.6202', 'cod_available' => false],
            ],
            // Jordan
            'JO' => [
                ['name_en' => 'Amman', 'name_ar' => 'عمان', 'latitude' => '31.9454', 'longitude' => '35.9284', 'cod_available' => false],
                ['name_en' => 'Zarqa', 'name_ar' => 'الزرقاء', 'latitude' => '32.0728', 'longitude' => '36.0878', 'cod_available' => false],
                ['name_en' => 'Irbid', 'name_ar' => 'إربد', 'latitude' => '32.5568', 'longitude' => '35.8469', 'cod_available' => false],
                ['name_en' => 'Aqaba', 'name_ar' => 'العقبة', 'latitude' => '29.5321', 'longitude' => '35.0063', 'cod_available' => false],
            ],
        ];

        foreach ($cities as $iso => $cityList) {
            $countryId = $countryIds[$iso] ?? null;
            if (!$countryId)
                continue;

            foreach ($cityList as $city) {
                $exists = DB::table('cities')
                    ->where('country_id', $countryId)
                    ->where('name_en', $city['name_en'])
                    ->exists();

                if (!$exists) {
                    DB::table('cities')->insert([
                        'id' => Str::uuid(),
                        'country_id' => $countryId,
                        'name_en' => $city['name_en'],
                        'name_ar' => $city['name_ar'],
                        'latitude' => $city['latitude'],
                        'longitude' => $city['longitude'],
                        'cod_available' => $city['cod_available'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
