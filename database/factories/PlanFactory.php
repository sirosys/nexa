<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PLN'.fake()->unique()->numerify('######'),
            'name' => 'Internet '.fake()->numberBetween(10, 100).' Mbps',
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 50000, 500000),
        ];
    }
}
