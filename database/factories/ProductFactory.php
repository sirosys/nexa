<?php

namespace Database\Factories;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRD'.fake()->unique()->numerify('######'),
            'type' => fake()->randomElement(ProductRequest::TYPES),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10000, 5000000),
            'unit' => fake()->randomElement(['pcs', 'bulan', 'unit']),
        ];
    }
}
