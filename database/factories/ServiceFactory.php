<?php

namespace Database\Factories;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SRV'.fake()->unique()->numerify('######'),
            'pin' => fake()->numerify('######'),
            'user_id' => User::factory()->afterCreating(fn (User $user) => $user->assignRole('customer')),
            'address' => fake()->address(),
            'residential_name' => fake()->company().' Residence',
            'subdistrict_id' => Subdistrict::factory(),
            'rw' => fake()->numerify('##'),
            'rt' => fake()->numerify('##'),
            'coverage_id' => Coverage::factory(),
            'package_id' => Package::factory()->state(['is_starter' => true]),
        ];
    }
}
