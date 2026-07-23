<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Field harga (total/discount/subtotal/grandtotal) sengaja default 0 —
     * nilai sungguhannya selalu dihitung ulang lewat
     * ServiceOrderService::syncProductsAndRecalculate(), bukan angka acak di
     * sini. `code` juga sengaja tidak diisi di sini — digenerate otomatis
     * lewat ServiceOrder::booted() (pola sama UserFactory/ServiceFactory).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
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
