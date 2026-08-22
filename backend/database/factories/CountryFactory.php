<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'id'            => (string) Str::uuid(),
            'iso_code_2'    => strtoupper(fake()->unique()->lexify('??')),
            'iso_code_3'    => strtoupper(fake()->unique()->lexify('???')),
            'name_en'       => $name,
            'name_ar'       => $name,
            'currency_code' => strtoupper(fake()->lexify('???')),
            'is_active'     => true,
        ];
    }
}
