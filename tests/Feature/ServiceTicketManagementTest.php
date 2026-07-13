<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketTechnicianAssignedNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class ServiceTicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    private function ticket(array $overrides = []): ServiceTicket
    {
        if (! isset($overrides['service_id'])) {
            $overrides['service_id'] = Service::factory()->create()->id;
        }

        return ServiceTicket::create(array_merge([
            'code' => 'TIK'.random_int(100000, 999999),
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'subject' => 'Internet lambat',
            'description' => 'Kecepatan turun drastis sejak kemarin.',
            'status' => ServiceTicket::STATUS_OPEN,
        ], $overrides));
    }

    public function test_superadmin_can_create_ticket_with_auto_generated_code(): void
    {
        $gateway = $this->fakeGateway();
        $service = Service::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/tickets', [
            'service_id' => $service->id,
            'category' => ServiceTicket::CATEGORY_BILLING,
            'subject' => 'Tagihan ganda',
            'description' => 'Pelanggan merasa ditagih dua kali bulan ini.',
        ]);

        $ticket = ServiceTicket::where('subject', 'Tagihan ganda')->firstOrFail();
        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertNotNull($ticket->code);
        $this->assertStringStartsWith('TIK', $ticket->code);
        $this->assertSame($service->id, $ticket->service_id);
        $this->assertSame(ServiceTicket::STATUS_OPEN, $ticket->status);

        $this->assertSame((string) $service->user->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $service->user_id,
            'type' => TicketCreatedNotification::class,
        ]);
    }

    public function test_ticket_creation_validates_category_and_service(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/tickets', [
            'service_id' => 999999,
            'category' => 'tidak-valid',
            'subject' => '',
            'description' => '',
        ]);

        $response->assertSessionHasErrors(['service_id', 'category', 'subject', 'description']);
    }

    public function test_superadmin_can_update_and_delete_ticket(): void
    {
        $ticket = $this->ticket();
        $newService = Service::factory()->create();

        $response = $this->actingAs($this->superadmin())->put("/tickets/{$ticket->id}", [
            'service_id' => $newService->id,
            'category' => ServiceTicket::CATEGORY_LAINNYA,
            'subject' => 'Subjek baru',
            'description' => 'Deskripsi baru',
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $ticket->refresh();
        $this->assertSame($newService->id, $ticket->service_id);
        $this->assertSame(ServiceTicket::CATEGORY_LAINNYA, $ticket->category);
        $this->assertSame('Subjek baru', $ticket->subject);

        $this->actingAs($this->superadmin())->delete("/tickets/{$ticket->id}")->assertRedirect(route('tickets.index'));
        $this->assertSoftDeleted('service_tickets', ['id' => $ticket->id]);
    }

    public function test_superadmin_can_assign_technician_to_technical_ticket(): void
    {
        $gateway = $this->fakeGateway();
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/tickets/{$ticket->id}/assign", [
            'technician_id' => $technician->id,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(ServiceTicket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($technician->id, $ticket->assigned_technician_id);
        $this->assertNotNull($ticket->assigned_by);
        $this->assertNotNull($ticket->claimed_at);

        $this->assertSame((string) $technician->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $technician->id,
            'type' => TicketTechnicianAssignedNotification::class,
        ]);
    }

    public function test_assign_rejected_for_non_technical_category(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_BILLING]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/tickets/{$ticket->id}/assign", [
            'technician_id' => $technician->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($ticket->fresh()->assigned_technician_id);
    }

    public function test_technician_can_claim_open_technical_ticket(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($technician)->post("/tickets/{$ticket->id}/claim");

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(ServiceTicket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($technician->id, $ticket->assigned_technician_id);
        $this->assertNull($ticket->assigned_by);
    }

    public function test_claim_rejected_when_already_claimed_by_another_technician(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $first = $this->withRole('technician');
        $second = $this->withRole('technician');

        $this->actingAs($first)->post("/tickets/{$ticket->id}/claim");
        $response = $this->actingAs($second)->post("/tickets/{$ticket->id}/claim");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($first->id, $ticket->fresh()->assigned_technician_id);
    }

    public function test_assigned_technician_can_resolve_technical_ticket(): void
    {
        $gateway = $this->fakeGateway();
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/tickets/{$ticket->id}/claim");

        $response = $this->actingAs($technician)->post("/tickets/{$ticket->id}/resolve", [
            'resolution_notes' => 'Sudah diperbaiki, kecepatan normal kembali.',
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(ServiceTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame($technician->id, $ticket->solved_by);
        $this->assertNotNull($ticket->solved_at);
        $this->assertSame('Sudah diperbaiki, kecepatan normal kembali.', $ticket->resolution_notes);

        $this->assertSame((string) $ticket->service->user->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $ticket->service->user_id,
            'type' => TicketResolvedNotification::class,
        ]);
    }

    public function test_technician_not_assigned_cannot_resolve_technical_ticket(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $assigned = $this->withRole('technician');
        $other = $this->withRole('technician');
        $this->actingAs($assigned)->post("/tickets/{$ticket->id}/claim");

        $response = $this->actingAs($other)->post("/tickets/{$ticket->id}/resolve", []);

        $response->assertForbidden();
    }

    public function test_superadmin_cannot_resolve_technical_ticket_without_override(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_TEKNIS]);
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/tickets/{$ticket->id}/claim");

        $response = $this->actingAs($this->superadmin())->post("/tickets/{$ticket->id}/resolve", []);

        $response->assertForbidden();
    }

    public function test_superadmin_can_resolve_non_technical_ticket_directly(): void
    {
        $gateway = $this->fakeGateway();
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_BILLING]);
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->post("/tickets/{$ticket->id}/resolve", [
            'resolution_notes' => 'Sudah dikoreksi di sistem billing.',
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(ServiceTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame($superadmin->id, $ticket->solved_by);

        $this->assertSame((string) $ticket->service->user->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $ticket->service->user_id,
            'type' => TicketResolvedNotification::class,
        ]);
    }

    public function test_technician_cannot_resolve_non_technical_ticket(): void
    {
        $ticket = $this->ticket(['category' => ServiceTicket::CATEGORY_BILLING]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($technician)->post("/tickets/{$ticket->id}/resolve", []);

        $response->assertForbidden();
    }

    public function test_technician_cannot_create_update_or_delete_tickets(): void
    {
        $ticket = $this->ticket();
        $technician = $this->withRole('technician');

        $this->actingAs($technician)->post('/tickets', [
            'service_id' => $ticket->service_id,
            'category' => ServiceTicket::CATEGORY_LAINNYA,
            'subject' => 'x',
            'description' => 'y',
        ])->assertForbidden();

        $this->actingAs($technician)->put("/tickets/{$ticket->id}", [
            'service_id' => $ticket->service_id,
            'category' => ServiceTicket::CATEGORY_LAINNYA,
            'subject' => 'x',
            'description' => 'y',
        ])->assertForbidden();

        $this->actingAs($technician)->delete("/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_non_superadmin_non_technician_roles_forbidden_from_ticket_routes(): void
    {
        $ticket = $this->ticket();

        foreach (['finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/tickets')->assertForbidden();
            $this->actingAs($staff)->get("/tickets/{$ticket->id}")->assertForbidden();
        }
    }

    public function test_index_and_show_render_for_superadmin_and_technician(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->superadmin())->get('/tickets')->assertOk();
        $this->actingAs($this->superadmin())->get("/tickets/{$ticket->id}")->assertOk();

        $technician = $this->withRole('technician');
        $this->actingAs($technician)->get('/tickets')->assertOk();
        $this->actingAs($technician)->get("/tickets/{$ticket->id}")->assertOk();
    }

    public function test_search_services_returns_browse_list_on_empty_query(): void
    {
        Service::factory()->count(3)->create();

        $response = $this->actingAs($this->superadmin())->getJson('/tickets/services/search');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }
}
