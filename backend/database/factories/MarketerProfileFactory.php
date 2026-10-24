<?php

namespace Database\Factories;

use App\Enums\MarketerGender;
use App\Models\Marketer;
use App\Models\MarketerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketerProfile>
 */
class MarketerProfileFactory extends Factory
{
    protected $model = MarketerProfile::class;

    public function definition(): array
    {
        return [
            'marketer_id' => Marketer::factory(),
            'profile_slug' => Str::lower(Str::random(10)),
            'gender' => null,
        ];
    }

    public function male(): static
    {
        return $this->state(['gender' => MarketerGender::Male]);
    }

    public function female(): static
    {
        return $this->state(['gender' => MarketerGender::Female]);
    }
}
