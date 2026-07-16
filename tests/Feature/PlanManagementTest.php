<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
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

    public function test_superadmin_can_create_plan_with_auto_generated_code(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/plans', [
            'name' => 'Internet Basic 10 Mbps',
            'description' => 'Kecepatan download 10 Mbps / upload 5 Mbps.',
            'price' => 100000,
        ]);

        $response->assertRedirect(route('plans.index'));

        $plan = Plan::where('name', 'Internet Basic 10 Mbps')->firstOrFail();
        $this->assertNotNull($plan->code);
        $this->assertStringStartsWith('PLN', $plan->code);
        $this->assertSame(100000.0, (float) $plan->price);
    }

    public function test_name_and_price_are_required(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/plans', []);

        $response->assertSessionHasErrors(['name', 'price']);
    }

    public function test_superadmin_can_update_plan(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create(['name' => 'Internet Lama']);

        $response = $this->actingAs($superadmin)->put("/plans/{$plan->id}", [
            'name' => 'Internet Baru',
            'price' => 200000,
        ]);

        $response->assertRedirect(route('plans.index'));
        $this->assertSame('Internet Baru', $plan->fresh()->name);
    }

    public function test_superadmin_can_delete_plan_without_dependents(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/plans/{$plan->id}");

        $response->assertRedirect(route('plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_plan_used_by_package_cannot_be_deleted(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();
        Package::factory()->create(['plan_id' => $plan->id, 'plan_qty' => 1]);

        $this->actingAs($superadmin)->delete("/plans/{$plan->id}");

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();

        $this->actingAs($superadmin)->get('/plans/create')->assertOk();
        $this->actingAs($superadmin)->get("/plans/{$plan->id}/edit")->assertOk();
    }

    public function test_listing_shows_plans(): void
    {
        $superadmin = $this->superadmin();
        Plan::factory()->create(['name' => 'Internet Premium 50 Mbps']);

        $response = $this->actingAs($superadmin)->get('/plans');

        $response->assertOk();
        $response->assertSee('Internet Premium 50 Mbps');
    }

    public function test_superadmin_can_view_plan_detail(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create(['name' => 'Internet Premium 50 Mbps']);

        $response = $this->actingAs($superadmin)->get(route('plans.show', $plan));

        $response->assertOk();
        $response->assertSee('Internet Premium 50 Mbps');
    }

    /**
     * Gate `/plans` cuma untuk superadmin, konsisten dengan gate
     * `/products`/`/packages` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_plan_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/plans')->assertForbidden();
        }
    }
}
