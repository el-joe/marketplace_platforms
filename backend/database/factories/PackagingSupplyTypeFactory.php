<?php

namespace Database\Factories;

use App\Models\PackagingSupplyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagingSupplyType>
 */
class PackagingSupplyTypeFactory extends Factory
{
    protected $model = PackagingSupplyType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => 'pt_'.fake()->unique()->bothify('????????'),
            'name_en' => ucfirst($name),
            'name_ar' => 'نوع '.$name,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
