<?php

namespace Tests\Feature\Api\V1;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_customer_only_sees_own_services_in_list(): void
    {
        $customer = $this->customer();
        $ownService = Service::factory()->create(['user_id' => $customer->id]);
        Service::factory()->create(); // milik customer lain

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/services');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', $ownService->code);
    }

    public function test_customer_can_view_own_service_detail(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $response = $this->getJson("/api/v1/services/{$service->code}");

        $response->assertOk()->assertJsonPath('data.code', $service->code);
    }

    public function test_service_belonging_to_another_customer_returns_404_not_403(): void
    {
        $customer = $this->customer();
        $otherService = Service::factory()->create();

        Sanctum::actingAs($customer);

        $response = $this->getJson("/api/v1/services/{$otherService->code}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/services')->assertStatus(401);
    }
}
