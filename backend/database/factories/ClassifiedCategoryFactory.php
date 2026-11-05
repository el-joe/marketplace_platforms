<?php

namespace Database\Factories;

use App\Models\ClassifiedCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassifiedCategory>
 */
class ClassifiedCategoryFactory extends Factory
{
    protected $model = ClassifiedCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name_en' => ucfirst($name),
            'name_ar' => 'قسم '.$name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function childOf(ClassifiedCategory $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }

    /**
     * @param  list<string>  $placements  ClassifiedPlacement values
     */
    public function placedOn(array $placements): static
    {
        return $this->state(['placements' => $placements]);
    }
}
