<?php

namespace Database\Factories;

use App\Enums\MarketerGender;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marketer>
 */
class MarketerFactory extends Factory
{
    protected $model = Marketer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9715'.fake()->unique()->numerify('########'),
            'global_status' => 'active',
            'country_id' => Country::factory(),
            'approved_at' => now(),
            'onboarding_completed_at' => now(),
        ];
    }

    /** Create the public profile with the given gender (male|female|null = not set). */
    public function withProfile(MarketerGender|string|null $gender = null): static
    {
        return $this->afterCreating(function (Marketer $marketer) use ($gender) {
            MarketerProfile::factory()->for($marketer)->create(['gender' => $gender]);
        });
    }
}
