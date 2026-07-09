<?php

namespace Database\Factories;

use App\Models\Pop;
use App\Models\Subdistrict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pop>
 */
class PopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'POP'.fake()->unique()->numerify('######'),
            'name' => 'PoP '.fake()->city(),
            'subdistrict_id' => Subdistrict::factory(),
            'serial' => fake()->bothify('SN-########'),
            'model' => fake()->randomElement(['MikroTik RB1100', 'MikroTik CCR2004', 'Huawei MA5800']),
            'location' => fake()->address(),
            'token' => fake()->uuid(),
        ];
    }
}
