<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'id'          => (string) Str::uuid(),
            'name_en'     => ucfirst($name),
            'name_ar'     => $name,
            'slug'        => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'commission_rate' => 5.00,
            'is_active'   => true,
            'is_visible'  => true,
        ];
    }
}
