<?php

namespace Database\Factories;

use App\Models\Coverage;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coverage>
 */
class CoverageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'COV'.fake()->unique()->numerify('######'),
            'site_id' => Site::factory(),
            'name' => 'Cakupan '.fake()->streetName(),
            'description' => fake()->sentence(),
        ];
    }
}
