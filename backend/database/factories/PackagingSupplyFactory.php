<?php

namespace Database\Factories;

use App\Models\PackagingSupply;
use App\Models\PackagingSupplyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagingSupply>
 */
class PackagingSupplyFactory extends Factory
{
    protected $model = PackagingSupply::class;

    public function definition(): array
    {
        return [
            'name_en' => 'Supply '.fake()->unique()->word(),
            'name_ar' => 'مستلزم '.fake()->word(),
            'packaging_supply_type_id' => PackagingSupplyType::factory(),
            'size' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
