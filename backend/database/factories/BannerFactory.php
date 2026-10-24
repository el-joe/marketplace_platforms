<?php

namespace Database\Factories;

use App\Enums\BannerAudience;
use App\Enums\BannerDeviceTarget;
use App\Enums\BannerGenderScope;
use App\Enums\BannerStatus;
use App\Models\Banner;
use App\Models\Marketer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'country_id' => null,
            'name' => fake()->unique()->words(3, true),
            'placement_code' => 'homepage_hero',
            'title_en' => fake()->sentence(3),
            'title_ar' => fake()->sentence(3),
            'cta_url' => '/',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => BannerStatus::Active,
            'device_target' => BannerDeviceTarget::All,
            'audience' => BannerAudience::All,
            'priority' => 0,
            'is_global' => false,
            'gender_scope' => BannerGenderScope::All,
            'created_by_admin_id' => (string) Str::uuid(),
        ];
    }

    public function placement(string $code): static
    {
        return $this->state(['placement_code' => $code]);
    }

    /** Global marketer banner for the given gender scope (all|male|female). */
    public function marketerGlobal(string $placement = 'marketers_list_hero', string $scope = 'all'): static
    {
        return $this->state([
            'placement_code' => $placement,
            'is_global' => true,
            'gender_scope' => $scope,
        ]);
    }

    /** Non-global marketer banner shown only inside the given marketers' details pages. */
    public function forMarketers(Marketer|array $marketers, string $placement = 'marketer_profile_slider'): static
    {
        $ids = collect(is_array($marketers) ? $marketers : [$marketers])->map(fn (Marketer $m) => $m->getKey())->all();

        return $this->state(['placement_code' => $placement, 'is_global' => false])
            ->afterCreating(fn (Banner $banner) => $banner->marketers()->attach($ids));
    }

    public function inactive(): static
    {
        return $this->state(['status' => BannerStatus::Inactive]);
    }

    public function expired(): static
    {
        return $this->state(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);
    }
}
