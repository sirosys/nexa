<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'REC'.fake()->unique()->numerify('######'),
            'service_order_id' => ServiceOrder::factory(),
            'xendit_payment_request_id' => 'pr-'.fake()->unique()->uuid(),
            'amount' => 0,
            'status' => 'PENDING',
            'checkout_url' => fake()->url(),
        ];
    }
}
