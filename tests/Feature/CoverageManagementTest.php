<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageManagementTest extends TestCase
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

    public function test_superadmin_can_create_coverage_with_auto_generated_code(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create();

        $response = $this->actingAs($superadmin)->post('/coverages', [
            'site_id' => $site->id,
            'name' => 'Cakupan Blok A',
            'description' => 'RW 01-05.',
        ]);

        $response->assertRedirect(route('coverages.index'));

        $coverage = Coverage::where('name', 'Cakupan Blok A')->firstOrFail();
        $this->assertNotNull($coverage->code);
        $this->assertStringStartsWith('COV', $coverage->code);
        $this->assertSame($site->id, $coverage->site_id);
    }

    public function test_site_id_must_exist(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/coverages', [
            'site_id' => 999999,
            'name' => 'Cakupan Tidak Valid',
        ]);

        $response->assertSessionHasErrors('site_id');
    }

    public function test_superadmin_can_update_coverage(): void
    {
        $superadmin = $this->superadmin();
        $coverage = Coverage::factory()->create(['name' => 'Cakupan Lama']);

        $response = $this->actingAs($superadmin)->put("/coverages/{$coverage->id}", [
            'site_id' => $coverage->site_id,
            'name' => 'Cakupan Baru',
        ]);

        $response->assertRedirect(route('coverages.index'));
        $this->assertSame('Cakupan Baru', $coverage->fresh()->name);
    }

    public function test_superadmin_can_delete_coverage(): void
    {
        $superadmin = $this->superadmin();
        $coverage = Coverage::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/coverages/{$coverage->id}");

        $response->assertRedirect(route('coverages.index'));
        $this->assertDatabaseMissing('coverages', ['id' => $coverage->id]);
    }

    public function test_listing_shows_coverages(): void
    {
        $superadmin = $this->superadmin();
        Coverage::factory()->create(['name' => 'Cakupan Blok B']);

        $response = $this->actingAs($superadmin)->get('/coverages');

        $response->assertOk();
        $response->assertSee('Cakupan Blok B');
    }

    public function test_superadmin_can_view_coverage_detail(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create(['name' => 'Site Induk']);
        $coverage = Coverage::factory()->create(['site_id' => $site->id, 'name' => 'Cakupan Detail']);

        $response = $this->actingAs($superadmin)->get(route('coverages.show', $coverage));

        $response->assertOk();
        $response->assertSee('Cakupan Detail');
        $response->assertSee('Site Induk');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $coverage = Coverage::factory()->create();

        $this->actingAs($superadmin)->get('/coverages/create')->assertOk();
        $this->actingAs($superadmin)->get("/coverages/{$coverage->id}/edit")->assertOk();
    }

    /**
     * Gate `/coverages` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_coverage_routes(): void
    {
        foreach (['technician', 'finance', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/coverages')->assertForbidden();
        }
    }
}
