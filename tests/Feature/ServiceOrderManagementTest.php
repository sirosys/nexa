<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ServiceOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_superadmin_can_create_service_order_with_auto_generated_code(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 100000, 'discount' => 0],
            ],
        ]);

        $response->assertRedirect(route('service-orders.index'));

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();
        $this->assertNotNull($serviceOrder->code);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{16}$/', $serviceOrder->code);
    }

    public function test_totals_calculated_correctly_from_line_items_including_per_line_discount(): void
    {
        $service = Service::factory()->create();
        // price=0 — sengaja mengisolasi test ini ke perhitungan baris
        // produk saja, tanpa kontribusi packages.price (SATU-SATUNYA acuan
        // harga paket sejak packages.plan_price dihapus, lihat CLAUDE.md
        // "Product & Package") yang sekarang ikut ditambahkan ke total.
        $package = Package::factory()->create(['price' => 0]);
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'tax' => 5000,
            'admin_fee' => 2500,
            'products' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'price' => 100000, 'discount' => 10000],
                ['product_id' => $productB->id, 'quantity' => 1, 'price' => 50000, 'discount' => 0],
            ],
        ]);

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();

        // total = (2*100000) + (1*50000) = 250000, sebelum diskon apa pun.
        $this->assertEquals(250000, (float) $serviceOrder->total);
        // discount = SUM(service_order_products.discount) = 10000.
        $this->assertEquals(10000, (float) $serviceOrder->discount);
        // subtotal = total - discount = 240000.
        $this->assertEquals(240000, (float) $serviceOrder->subtotal);
        // grandtotal = subtotal + tax + admin_fee = 240000+5000+2500.
        $this->assertEquals(247500, (float) $serviceOrder->grandtotal);
    }

    public function test_is_starter_snapshotted_from_selected_package(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $starterPackage = Package::factory()->create(['is_starter' => true]);
        $nonStarterPackage = Package::factory()->create(['is_starter' => false]);
        $product = Product::factory()->create();

        $this->actingAs($superadmin)->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $starterPackage->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();
        $this->assertTrue($serviceOrder->is_starter);

        $this->actingAs($superadmin)->put("/service-orders/{$serviceOrder->code}", [
            'service_id' => $service->id,
            'package_id' => $nonStarterPackage->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $serviceOrder->refresh();
        $this->assertFalse($serviceOrder->is_starter);
    }

    public function test_guard_duplicate_product_in_submission(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10000],
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 10000],
            ],
        ]);

        $response->assertSessionHasErrors('products');
    }

    /**
     * Rule::exists() Laravel query langsung ke tabel, tidak menghormati
     * global scope SoftDeletes milik Service — ServiceOrderRequest wajib
     * eksplisit whereNull('deleted_at') supaya celah ini tertutup.
     */
    public function test_service_id_must_not_reference_soft_deleted_service(): void
    {
        $service = Service::factory()->create();
        $service->delete();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $response->assertSessionHasErrors('service_id');
    }

    public function test_service_id_and_package_id_must_exist(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => 999999,
            'package_id' => 999999,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $response->assertSessionHasErrors(['service_id', 'package_id']);
    }

    /**
     * total/subtotal/discount/grandtotal sengaja tidak ada di
     * ServiceOrderRequest::rules(), jadi walau dikirim dari client, angka
     * itu tidak pernah lolos ke $data tervalidasi — ServiceOrderService
     * selalu menghitung ulang penuh dari products[].
     */
    public function test_totals_cannot_be_overridden_from_request(): void
    {
        $service = Service::factory()->create();
        // price=0 — isolasi dari kontribusi packages.price, lihat komentar
        // di test_totals_calculated_correctly_from_line_items_including_per_line_discount.
        $package = Package::factory()->create(['price' => 0]);
        $product = Product::factory()->create();

        $this->actingAs($this->superadmin())->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'total' => 999999999,
            'subtotal' => 999999999,
            'discount' => 999999999,
            'grandtotal' => 999999999,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();
        $this->assertEquals(10000, (float) $serviceOrder->total);
        $this->assertEquals(10000, (float) $serviceOrder->grandtotal);
    }

    public function test_superadmin_can_update_service_order_and_recalculate_totals_when_line_items_change(): void
    {
        $service = Service::factory()->create();
        // price=0 — isolasi dari kontribusi packages.price, lihat komentar
        // di test_totals_calculated_correctly_from_line_items_including_per_line_discount.
        $package = Package::factory()->create(['price' => 0]);
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $serviceOrder = ServiceOrder::factory()->create(['service_id' => $service->id, 'package_id' => $package->id]);
        app(ServiceOrderService::class)->syncProductsAndRecalculate($serviceOrder, [
            ['product_id' => $productA->id, 'quantity' => 1, 'price' => 10000],
        ]);

        $this->actingAs($this->superadmin())->put("/service-orders/{$serviceOrder->code}", [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $productB->id, 'quantity' => 3, 'price' => 20000],
            ],
        ]);

        $serviceOrder->refresh();
        $this->assertEquals(60000, (float) $serviceOrder->total);
        $this->assertCount(1, $serviceOrder->products);
        $this->assertSame($productB->id, $serviceOrder->products->first()->id);
    }

    public function test_deleting_service_order_is_soft_delete(): void
    {
        $serviceOrder = ServiceOrder::factory()->create();

        $response = $this->actingAs($this->superadmin())->delete("/service-orders/{$serviceOrder->code}");

        $response->assertRedirect(route('service-orders.index'));
        $this->assertSoftDeleted('service_orders', ['id' => $serviceOrder->id]);
    }

    public function test_soft_deleted_service_order_hidden_from_listing(): void
    {
        $serviceOrder = ServiceOrder::factory()->create();
        $serviceOrder->delete();

        $response = $this->actingAs($this->superadmin())->get('/service-orders');

        $response->assertOk();
        $response->assertDontSee($serviceOrder->code);
    }

    public function test_restrict_on_delete_blocks_deleting_referenced_package(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();
        ServiceOrder::factory()->create(['package_id' => $package->id]);

        $this->actingAs($superadmin)->delete("/packages/{$package->id}");

        $this->assertDatabaseHas('packages', ['id' => $package->id]);
    }

    /**
     * Service::delete() normal itu soft (tidak menyentuh FK sama sekali) —
     * berbeda dari test restrict-on-delete lain yang lewat HTTP, di sini
     * forceDelete() dipanggil langsung di level model supaya benar-benar
     * memicu constraint restrictOnDelete pada service_orders.service_id.
     */
    public function test_restrict_on_delete_blocks_force_deleting_referenced_service(): void
    {
        $service = Service::factory()->create();
        ServiceOrder::factory()->create(['service_id' => $service->id]);

        $this->expectException(QueryException::class);

        $service->forceDelete();
    }

    public function test_search_services_endpoint_returns_browse_list_when_query_is_empty(): void
    {
        $superadmin = $this->superadmin();
        Service::factory()->count(2)->create();

        $response = $this->actingAs($superadmin)->getJson('/service-orders/services/search');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_search_services_endpoint_matches_code_address_customer_name_or_phone(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->customer();
        $customer->update(['name' => 'Budi Santoso']);
        $service = Service::factory()->create(['user_id' => $customer->id, 'address' => 'Jl. Mangga No. 1']);
        Service::factory()->create(['address' => 'Jl. Lain No. 2']);

        $response = $this->actingAs($superadmin)->getJson('/service-orders/services/search?q=Budi');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_search_services_endpoint_excludes_soft_deleted_services(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $service->delete();

        $response = $this->actingAs($superadmin)->getJson('/service-orders/services/search');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_listing_shows_service_orders_and_searches_by_code_or_service_code(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $serviceOrder = ServiceOrder::factory()->create(['service_id' => $service->id]);

        $this->actingAs($superadmin)->get('/service-orders?q='.$serviceOrder->code)->assertOk()->assertSee($serviceOrder->code);
        $this->actingAs($superadmin)->get('/service-orders?q='.$service->code)->assertOk()->assertSee($serviceOrder->code);
    }

    public function test_superadmin_can_view_service_order_detail_with_products(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create(['name' => 'Modem Detail']);

        $this->actingAs($superadmin)->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 100000, 'discount' => 0],
            ],
        ]);

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();

        $response = $this->actingAs($superadmin)->get(route('service-orders.show', $serviceOrder));

        $response->assertOk();
        $response->assertSee($serviceOrder->code);
        $response->assertSee('Modem Detail');
    }

    /**
     * Baris Plan di tabel line item cuma muncul kalau Order Layanan-nya
     * punya plan_id — regression untuk Blade `@if ($serviceOrder->plan)`
     * di service-orders/show.
     */
    public function test_service_order_detail_renders_plan_line_when_present(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $plan = Plan::factory()->create(['name' => 'Internet Detail']);
        $package = Package::factory()->create(['plan_id' => $plan->id, 'price' => 150000, 'plan_qty' => 1]);
        $product = Product::factory()->create();

        $this->actingAs($superadmin)->post('/service-orders', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 50000, 'discount' => 0],
            ],
        ]);

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($plan->id, $serviceOrder->plan_id);
        $this->assertEquals(200000, (float) $serviceOrder->grandtotal);

        $response = $this->actingAs($superadmin)->get(route('service-orders.show', $serviceOrder));

        $response->assertOk();
        $response->assertSee('Internet Detail');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $serviceOrder = ServiceOrder::factory()->create();

        $this->actingAs($superadmin)->get('/service-orders/create')->assertOk();
        $this->actingAs($superadmin)->get("/service-orders/{$serviceOrder->code}/edit")->assertOk();
    }

    public function test_service_order_show_route_uses_code_not_raw_id(): void
    {
        $superadmin = $this->superadmin();
        $serviceOrder = ServiceOrder::factory()->create();

        $this->actingAs($superadmin)->get(route('service-orders.show', $serviceOrder))->assertOk();
        $this->actingAs($superadmin)->get("/service-orders/{$serviceOrder->id}")->assertNotFound();
    }

    public function test_non_superadmin_roles_cannot_access_service_order_routes(): void
    {
        $customer = $this->withRole('customer');

        $this->actingAs($customer)->get('/service-orders')->assertForbidden();
    }

    /**
     * Role 'sales' dihapus total 2026-07-17 — finance & technician sekarang
     * sama-sama dapat service_orders.view (permission registrasi pelanggan
     * dibagikan ke semua staff, lihat CLAUDE.md "Authorization / Role &
     * Permission").
     */
    public function test_finance_and_technician_roles_can_view_service_order_routes(): void
    {
        foreach (['finance', 'technician'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/service-orders')->assertOk();
        }
    }
}
