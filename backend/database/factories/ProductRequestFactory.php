<?php

namespace Database\Factories;

use App\Enums\ProductRequestStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Marketer;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRequest>
 */
class ProductRequestFactory extends Factory
{
    protected $model = ProductRequest::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'requester_type' => ProductRequest::REQUESTER_VENDOR,
            'vendor_id' => Vendor::factory(),
            'marketer_id' => null,
            'name_en' => $name,
            'name_ar' => $name.' (ar)',
            'category_id' => Category::factory(),
            'gtin' => fake()->unique()->numerify('#############'),
            'model_number' => strtoupper(fake()->bothify('??-####')),
            'description' => fake()->paragraph(4),
            'images' => ['product-requests/sample/image-1.jpg'],
            'status' => ProductRequestStatus::Pending,
        ];
    }

    public function vendor(?Vendor $vendor = null): static
    {
        return $this->state(fn (): array => [
            'requester_type' => ProductRequest::REQUESTER_VENDOR,
            'vendor_id' => $vendor?->id ?? Vendor::factory(),
            'marketer_id' => null,
        ]);
    }

    public function marketer(?Marketer $marketer = null): static
    {
        return $this->state(fn (): array => [
            'requester_type' => ProductRequest::REQUESTER_MARKETER,
            'vendor_id' => null,
            'marketer_id' => $marketer?->id ?? Marketer::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductRequestStatus::Approved,
            'reviewed_by' => Admin::factory(),
            'reviewed_at' => now(),
            'product_id' => Product::factory(),
        ]);
    }

    public function rejected(string $reason = 'The product does not meet catalog requirements.'): static
    {
        return $this->state(fn (): array => [
            'status' => ProductRequestStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => Admin::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
