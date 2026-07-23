<?php

namespace Tests\Feature\Api\V1;

use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_customer_can_list_and_view_own_invoices(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        $serviceOrder = ServiceOrder::factory()->create(['service_id' => $service->id, 'grandtotal' => 150000]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/services/{$service->code}/invoices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $serviceOrder->code);

        $this->getJson("/api/v1/services/{$service->code}/invoices/{$serviceOrder->code}")
            ->assertOk()
            ->assertJsonPath('data.code', $serviceOrder->code)
            ->assertJsonPath('data.grandtotal', 150000);
    }

    public function test_invoice_status_derived_from_timestamps(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        Sanctum::actingAs($customer);

        $draft = ServiceOrder::factory()->create(['service_id' => $service->id]);
        $this->getJson("/api/v1/services/{$service->code}/invoices/{$draft->code}")
            ->assertJsonPath('data.status', 'draft');

        $invoiced = ServiceOrder::factory()->create(['service_id' => $service->id, 'invoiced_at' => now()]);
        $this->getJson("/api/v1/services/{$service->code}/invoices/{$invoiced->code}")
            ->assertJsonPath('data.status', 'invoiced');

        $settled = ServiceOrder::factory()->create(['service_id' => $service->id, 'invoiced_at' => now(), 'settled_at' => now()]);
        $this->getJson("/api/v1/services/{$service->code}/invoices/{$settled->code}")
            ->assertJsonPath('data.status', 'settled');

        // Presedensi: canceled menang atas settled kalau kedua timestamp
        // somehow terisi (lihat App\Support\ServiceOrderStatus).
        $canceledAndSettled = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now(),
            'settled_at' => now(),
            'canceled_at' => now(),
        ]);
        $this->getJson("/api/v1/services/{$service->code}/invoices/{$canceledAndSettled->code}")
            ->assertJsonPath('data.status', 'canceled');
    }

    public function test_checkout_url_is_null_without_receipt(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        $serviceOrder = ServiceOrder::factory()->create(['service_id' => $service->id]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/services/{$service->code}/invoices/{$serviceOrder->code}")
            ->assertJsonPath('data.checkout_url', null);
    }

    public function test_checkout_url_reflects_existing_receipt(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        $serviceOrder = ServiceOrder::factory()->create(['service_id' => $service->id]);
        $receipt = Receipt::factory()->create(['service_order_id' => $serviceOrder->id, 'checkout_url' => 'https://nexa.test/pay/abc']);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/services/{$service->code}/invoices/{$serviceOrder->code}")
            ->assertJsonPath('data.checkout_url', $receipt->checkout_url);
    }

    public function test_invoice_belonging_to_foreign_service_returns_404(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        $otherService = Service::factory()->create();
        $otherServiceOrder = ServiceOrder::factory()->create(['service_id' => $otherService->id]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/services/{$service->code}/invoices/{$otherServiceOrder->code}")
            ->assertStatus(404);

        $this->getJson("/api/v1/services/{$otherService->code}/invoices")
            ->assertStatus(404);
    }
}
