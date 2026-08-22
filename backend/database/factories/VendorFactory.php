<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $storeName = fake()->unique()->company();

        return [
            'name'           => fake()->name(),
            'email'          => fake()->unique()->safeEmail(),
            'phone'          => fake()->unique()->numerify('01#########'),
            'password'       => Hash::make('password'),
            'store_name'     => $storeName,
            'store_slug'     => Str::slug($storeName) . '-' . Str::lower(Str::random(6)),
            'business_type'  => 'individual',
            'payout_schedule' => 'monthly',
            'global_status'  => 'active',
            'approved_at'    => now(),
        ];
    }
}
