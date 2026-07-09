<?php

namespace Database\Factories;

use App\Models\Subdistrict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subdistrict>
 */
class SubdistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => fake()->unique()->numberBetween(1000000, 9999999),
            'city_id' => fake()->numberBetween(1000, 9999),
            'province_id' => fake()->numberBetween(10, 99),
            'code' => fake()->unique()->numerify('##.##.##.####'),
            'zip' => fake()->numberBetween(10000, 99999),
            'type' => 'Kecamatan',
            'name' => fake()->city(),
            'district_name' => fake()->citySuffix().' '.fake()->city(),
            'city_type' => 'Kabupaten',
            'city_name' => fake()->city(),
            'province_name' => fake()->state(),
        ];
    }
}
