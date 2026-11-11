<?php

namespace Database\Factories;

use App\Models\SizeGuide;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeGuide>
 */
class SizeGuideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'name' => fake()->words(2, true),
            'unit' => 'cm',
            'columns' => SizeGuide::abayaTemplate()['columns'],
        ];
    }

    /**
     * Seeds the abaya template rows after creation.
     */
    public function withAbayaRows(): static
    {
        return $this->afterCreating(fn (SizeGuide $guide) => $guide->syncRows(SizeGuide::abayaTemplate()['rows']));
    }
}
