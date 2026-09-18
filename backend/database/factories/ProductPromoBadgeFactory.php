<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPromoBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPromoBadge>
 */
class ProductPromoBadgeFactory extends Factory
{
    protected $model = ProductPromoBadge::class;

    public function definition(): array
    {
        return [
            'product_id'     => Product::factory(),
            'label_en'       => fake()->randomElement(['Free next-day delivery', 'Cash on delivery available', 'Extra 10% off with card']),
            'label_ar'       => fake()->randomElement(['توصيل مجاني في اليوم التالي', 'الدفع عند الاستلام متاح', 'خصم إضافي 10% بالبطاقة']),
            'icon_key'       => fake()->randomElement(['car', 'truck']),
            'color_hex'      => '#1a1a2e',
            'text_color_hex' => '#FFFFFF',
            'sort_order'     => 0,
            'is_active'      => true,
        ];
    }
}
