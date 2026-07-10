<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Field harga (total/discount/subtotal/grandtotal) sengaja default 0 —
     * nilai sungguhannya selalu dihitung ulang lewat
     * SaleService::syncProductsAndRecalculate(), bukan angka acak di sini.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SAL'.fake()->unique()->numerify('######'),
            'service_id' => Service::factory(),
            'package_id' => Package::factory(),
            'is_starter' => false,
            'total' => 0,
            'discount' => 0,
            'subtotal' => 0,
            'tax' => 0,
            'admin_fee' => 0,
            'grandtotal' => 0,
        ];
    }
}
