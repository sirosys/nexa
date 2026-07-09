<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Pop;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopManagementTest extends TestCase
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

    public function test_superadmin_can_create_pop_with_auto_generated_code(): void
    {
        $subdistrict = Subdistrict::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/pops', [
            'name' => 'PoP Menteng',
            'subdistrict_id' => $subdistrict->id,
            'serial' => 'SN-12345678',
            'model' => 'MikroTik CCR2004',
            'location' => 'Jl. Contoh No. 1',
            'token' => 'rahasia-token',
        ]);

        $response->assertRedirect(route('pops.index'));

        $pop = Pop::where('name', 'PoP Menteng')->firstOrFail();
        $this->assertNotNull($pop->code);
        $this->assertStringStartsWith('POP', $pop->code);
        $this->assertSame($subdistrict->id, $pop->subdistrict_id);
        $this->assertSame('rahasia-token', $pop->token);
    }

    public function test_pop_token_is_hidden_from_serialization(): void
    {
        $pop = Pop::factory()->create(['token' => 'rahasia-token']);

        $this->assertArrayNotHasKey('token', $pop->toArray());
    }

    public function test_subdistrict_id_must_exist(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/pops', [
            'name' => 'PoP Tidak Valid',
            'subdistrict_id' => 999999,
        ]);

        $response->assertSessionHasErrors('subdistrict_id');
    }

    public function test_superadmin_can_update_pop_without_clearing_token_when_left_blank(): void
    {
        $superadmin = $this->superadmin();
        $pop = Pop::factory()->create(['name' => 'PoP Lama', 'token' => 'token-lama']);

        $response = $this->actingAs($superadmin)->put("/pops/{$pop->id}", [
            'name' => 'PoP Baru',
            'subdistrict_id' => $pop->subdistrict_id,
        ]);

        $response->assertRedirect(route('pops.index'));
        $pop->refresh();
        $this->assertSame('PoP Baru', $pop->name);
        $this->assertSame('token-lama', $pop->token);
    }

    public function test_superadmin_can_delete_pop(): void
    {
        $superadmin = $this->superadmin();
        $pop = Pop::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/pops/{$pop->id}");

        $response->assertRedirect(route('pops.index'));
        $this->assertDatabaseMissing('pops', ['id' => $pop->id]);
    }

    public function test_deleting_pop_with_coverage_is_restricted(): void
    {
        $superadmin = $this->superadmin();
        $pop = Pop::factory()->create();
        Coverage::factory()->create(['pop_id' => $pop->id]);

        $this->actingAs($superadmin)->delete("/pops/{$pop->id}");

        $this->assertDatabaseHas('pops', ['id' => $pop->id]);
    }

    public function test_listing_shows_pops(): void
    {
        $superadmin = $this->superadmin();
        Pop::factory()->create(['name' => 'PoP Kemang']);

        $response = $this->actingAs($superadmin)->get('/pops');

        $response->assertOk();
        $response->assertSee('PoP Kemang');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $pop = Pop::factory()->create();

        $this->actingAs($superadmin)->get('/pops/create')->assertOk();
        $this->actingAs($superadmin)->get("/pops/{$pop->id}/edit")->assertOk();
    }

    public function test_subdistrict_search_endpoint_returns_matches(): void
    {
        $superadmin = $this->superadmin();
        Subdistrict::factory()->create(['name' => 'Kebayoran Baru']);
        Subdistrict::factory()->create(['name' => 'Kebayoran Lama']);
        Subdistrict::factory()->create(['name' => 'Menteng']);

        $response = $this->actingAs($superadmin)->getJson('/subdistricts/search?q=Kebayoran');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    /**
     * Gate `/pops` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_pop_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/pops')->assertForbidden();
        }
    }
}
