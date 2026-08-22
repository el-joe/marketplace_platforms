<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id'   => Product::factory(),
            'sku'          => strtoupper(fake()->unique()->bothify('SKU-########')),
            'variant_name' => fake()->words(2, true),
            'is_default'   => true,
            'is_active'    => true,
            'position'     => 0,
        ];
    }
}
