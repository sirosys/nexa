<?php

namespace Tests\Feature\Api\V1;

use App\Models\Service;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class ServiceTicketApiTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    public function test_customer_can_create_ticket_for_own_service_and_notification_is_sent(): void
    {
        $gateway = $this->fakeGateway();
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/v1/services/{$service->code}/tickets", [
            'category' => ServiceTicket::CATEGORY_BILLING,
            'subject' => 'Tagihan tidak sesuai',
            'description' => 'Jumlah tagihan bulan ini berbeda dari biasanya.',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.subject', 'Tagihan tidak sesuai');

        $ticket = ServiceTicket::where('service_id', $service->id)->firstOrFail();
        $this->assertSame(ServiceTicket::STATUS_OPEN, $ticket->status);
        $this->assertSame((string) $customer->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'type' => TicketCreatedNotification::class,
        ]);
    }

    public function test_customer_cannot_create_ticket_for_foreign_service(): void
    {
        $this->fakeGateway();
        $customer = $this->customer();
        $otherService = Service::factory()->create();

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/v1/services/{$otherService->code}/tickets", [
            'category' => ServiceTicket::CATEGORY_BILLING,
            'subject' => 'Tagihan tidak sesuai',
            'description' => 'Deskripsi.',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('service_tickets', ['service_id' => $otherService->id]);
    }

    public function test_customer_can_list_and_view_own_tickets(): void
    {
        $customer = $this->customer();
        $service = Service::factory()->create(['user_id' => $customer->id]);
        $ticket = ServiceTicket::create([
            'code' => 'TIK000001',
            'service_id' => $service->id,
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'subject' => 'Internet lambat',
            'description' => 'Kecepatan turun drastis sejak kemarin.',
            'status' => ServiceTicket::STATUS_OPEN,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/services/{$service->code}/tickets")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $ticket->code);

        $this->getJson("/api/v1/tickets/{$ticket->code}")
            ->assertOk()
            ->assertJsonPath('data.code', $ticket->code);
    }

    public function test_foreign_ticket_returns_404(): void
    {
        $customer = $this->customer();
        $otherService = Service::factory()->create();
        $otherTicket = ServiceTicket::create([
            'code' => 'TIK000002',
            'service_id' => $otherService->id,
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'subject' => 'Internet lambat',
            'description' => 'Kecepatan turun drastis sejak kemarin.',
            'status' => ServiceTicket::STATUS_OPEN,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/tickets/{$otherTicket->code}")->assertStatus(404);
    }
}
