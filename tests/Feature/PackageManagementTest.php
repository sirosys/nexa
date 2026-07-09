<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageManagementTest extends TestCase
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

    public function test_superadmin_can_create_package_with_bundled_products(): void
    {
        $superadmin = $this->superadmin();
        $modem = Product::factory()->create(['price' => 350000]);
        $installation = Product::factory()->create(['price' => 150000]);

        $response = $this->actingAs($superadmin)->post('/packages', [
            'is_starter' => '1',
            'name' => 'Paket Gratis 3 Bulan',
            'description' => 'Promo pendaftaran baru.',
            'price' => 0,
            'products' => [
                ['product_id' => $modem->id, 'quantity' => 1, 'price' => 0],
                ['product_id' => $installation->id, 'quantity' => 1, 'price' => 0],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));

        $package = Package::where('name', 'Paket Gratis 3 Bulan')->firstOrFail();
        $this->assertNotNull($package->code);
        $this->assertStringStartsWith('PKG', $package->code);
        $this->assertTrue($package->is_starter);
        $this->assertCount(2, $package->products);
        $this->assertDatabaseHas('package_product', [
            'package_id' => $package->id,
            'product_id' => $modem->id,
            'quantity' => 1,
        ]);
    }

    public function test_package_requires_at_least_one_product(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Kosong',
            'price' => 100000,
            'products' => [],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_duplicate_product_in_same_package_is_rejected(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Duplikat',
            'price' => 100000,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 50000],
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 50000],
            ],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_superadmin_can_update_package_products(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();
        $originalProduct = Product::factory()->create();
        $package->products()->attach($originalProduct->id, ['quantity' => 1, 'price' => 100000]);

        $newProduct = Product::factory()->create();

        $response = $this->actingAs($superadmin)->put("/packages/{$package->id}", [
            'name' => $package->name,
            'price' => $package->price,
            'products' => [
                ['product_id' => $newProduct->id, 'quantity' => 2, 'price' => 75000],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $package->refresh();
        $this->assertCount(1, $package->products);
        $this->assertTrue($package->products->first()->is($newProduct));
    }

    public function test_superadmin_can_delete_package(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/packages/{$package->id}");

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    /**
     * Gate `/packages` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_package_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/packages')->assertForbidden();
        }
    }
}
