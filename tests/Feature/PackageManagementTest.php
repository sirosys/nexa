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
        $internet = Product::factory()->create(['type' => 'langganan', 'price' => 0]);
        $modem = Product::factory()->create(['price' => 350000]);
        $installation = Product::factory()->create(['price' => 150000]);

        $response = $this->actingAs($superadmin)->post('/packages', [
            'is_starter' => '1',
            'name' => 'Paket Gratis 3 Bulan',
            'description' => 'Promo pendaftaran baru.',
            'price' => 0,
            'base_product_id' => $internet->id,
            'products' => [
                ['product_id' => $internet->id, 'quantity' => 1, 'price' => 0],
                ['product_id' => $modem->id, 'quantity' => 1, 'price' => 0],
                ['product_id' => $installation->id, 'quantity' => 1, 'price' => 0],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));

        $package = Package::where('name', 'Paket Gratis 3 Bulan')->firstOrFail();
        $this->assertNotNull($package->code);
        $this->assertStringStartsWith('PKG', $package->code);
        $this->assertTrue($package->is_starter);
        $this->assertSame($internet->id, $package->base_product_id);
        $this->assertCount(3, $package->products);
        $this->assertDatabaseHas('package_product', [
            'package_id' => $package->id,
            'product_id' => $modem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(1, $package->duration_months);
    }

    public function test_base_product_id_is_required(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Tanpa Base Product',
            'price' => 100000,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('base_product_id');
    }

    public function test_base_product_id_must_be_among_bundled_products(): void
    {
        $bundled = Product::factory()->create(['type' => 'langganan']);
        $notBundled = Product::factory()->create(['type' => 'langganan']);

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Base Product Salah',
            'price' => 100000,
            'base_product_id' => $notBundled->id,
            'products' => [
                ['product_id' => $bundled->id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('base_product_id');
    }

    public function test_base_product_id_must_be_subscription_type(): void
    {
        $modem = Product::factory()->create(['type' => 'perangkat']);

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Base Product Bukan Langganan',
            'price' => 100000,
            'base_product_id' => $modem->id,
            'products' => [
                ['product_id' => $modem->id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('base_product_id');
    }

    public function test_duration_months_derived_from_subscription_product_quantity(): void
    {
        $superadmin = $this->superadmin();
        $internet = Product::factory()->create(['type' => 'langganan', 'price' => 150000]);
        $modem = Product::factory()->create(['type' => 'perangkat', 'price' => 350000]);

        $response = $this->actingAs($superadmin)->post('/packages', [
            'name' => 'Paket Internet 6 Bulan',
            'price' => 900000,
            'base_product_id' => $internet->id,
            'products' => [
                ['product_id' => $internet->id, 'quantity' => 6, 'price' => 150000],
                ['product_id' => $modem->id, 'quantity' => 1, 'price' => 350000],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));

        $package = Package::where('name', 'Paket Internet 6 Bulan')->firstOrFail();
        $this->assertSame(6, $package->duration_months);
    }

    public function test_uneven_subscription_product_quantities_are_rejected(): void
    {
        $superadmin = $this->superadmin();
        $internetA = Product::factory()->create(['type' => 'langganan']);
        $internetB = Product::factory()->create(['type' => 'langganan']);

        $response = $this->actingAs($superadmin)->post('/packages', [
            'name' => 'Paket Campur Aduk',
            'price' => 500000,
            'base_product_id' => $internetA->id,
            'products' => [
                ['product_id' => $internetA->id, 'quantity' => 3, 'price' => 100000],
                ['product_id' => $internetB->id, 'quantity' => 6, 'price' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_duration_months_recalculated_on_update(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();
        $originalProduct = Product::factory()->create(['type' => 'perangkat']);
        $package->products()->attach($originalProduct->id, ['quantity' => 1, 'price' => 100000]);

        $internet = Product::factory()->create(['type' => 'langganan']);

        $response = $this->actingAs($superadmin)->put("/packages/{$package->id}", [
            'name' => $package->name,
            'price' => $package->price,
            'base_product_id' => $internet->id,
            'products' => [
                ['product_id' => $internet->id, 'quantity' => 12, 'price' => 130000],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertSame(12, $package->fresh()->duration_months);
        $this->assertSame($internet->id, $package->fresh()->base_product_id);
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

        $newProduct = Product::factory()->create(['type' => 'langganan']);

        $response = $this->actingAs($superadmin)->put("/packages/{$package->id}", [
            'name' => $package->name,
            'price' => $package->price,
            'base_product_id' => $newProduct->id,
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

    public function test_superadmin_can_view_package_detail_with_bundled_products(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create(['name' => 'Paket Detail']);
        $product = Product::factory()->create(['name' => 'Modem Detail']);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 100000]);

        $response = $this->actingAs($superadmin)->get(route('packages.show', $package));

        $response->assertOk();
        $response->assertSee('Paket Detail');
        $response->assertSee('Modem Detail');
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
