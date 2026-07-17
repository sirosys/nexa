<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Subdistrict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SIT'.fake()->unique()->numerify('######'),
            'name' => 'Site '.fake()->city(),
            'subdistrict_id' => Subdistrict::factory(),
            'serial' => fake()->bothify('SN-########'),
            'model' => fake()->randomElement(['MikroTik RB1100', 'MikroTik CCR2004', 'Huawei MA5800']),
            'location' => fake()->address(),
            'token' => fake()->uuid(),
        ];
    }
}
