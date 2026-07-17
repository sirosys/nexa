<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
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

    public function test_superadmin_can_create_non_serialized_inventory_item(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);

        $response = $this->actingAs($this->superadmin())->post('/inventory-items', [
            'product_id' => $product->id,
            'is_serialized' => '0',
        ]);

        $item = InventoryItem::where('product_id', $product->id)->firstOrFail();
        $response->assertRedirect(route('inventory-items.show', $item));
        $this->assertNotNull($item->code);
        $this->assertStringStartsWith('INV', $item->code);
        $this->assertFalse($item->is_serialized);
        $this->assertSame(0, $item->quantity);
    }

    public function test_creating_inventory_item_rejects_non_perangkat_product(): void
    {
        $product = Product::factory()->create(['type' => 'jasa']);

        $response = $this->actingAs($this->superadmin())->post('/inventory-items', [
            'product_id' => $product->id,
            'is_serialized' => '0',
        ]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_creating_inventory_item_rejects_duplicate_product(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 0]);

        $response = $this->actingAs($this->superadmin())->post('/inventory-items', [
            'product_id' => $product->id,
            'is_serialized' => '0',
        ]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_stock_in_increases_quantity_for_non_serialized_item(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 5]);

        $response = $this->actingAs($this->superadmin())->post("/inventory-items/{$item->id}/stock-in", [
            'quantity' => 10,
            'notes' => 'Pembelian dari vendor A',
        ]);

        $response->assertRedirect(route('inventory-items.show', $item));
        $this->assertSame(15, $item->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 10,
        ]);
    }

    public function test_stock_in_creates_units_for_serialized_item(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => true, 'quantity' => 0]);

        $response = $this->actingAs($this->superadmin())->post("/inventory-items/{$item->id}/stock-in", [
            'serial_numbers' => "SN-001\nSN-002\nSN-002\n",
        ]);

        $response->assertRedirect(route('inventory-items.show', $item));
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(2, InventoryUnit::where('inventory_item_id', $item->id)->count());
        $this->assertDatabaseHas('inventory_units', ['serial_number' => 'SN-001', 'status' => InventoryUnit::STATUS_IN_STOCK]);
    }

    public function test_adjust_stock_can_increase_and_decrease_quantity(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 10]);

        $this->actingAs($this->superadmin())->post("/inventory-items/{$item->id}/adjust", [
            'delta' => -3,
            'notes' => 'Rusak',
        ]);

        $this->assertSame(7, $item->fresh()->quantity);
    }

    public function test_adjust_stock_rejects_negative_result(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 2]);

        $response = $this->actingAs($this->superadmin())->post("/inventory-items/{$item->id}/adjust", [
            'delta' => -5,
            'notes' => 'Terlalu banyak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_adjust_stock_rejects_serialized_item(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => true, 'quantity' => 2]);

        $response = $this->actingAs($this->superadmin())->post("/inventory-items/{$item->id}/adjust", [
            'delta' => 1,
            'notes' => 'Coba adjust serial',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_superadmin_can_delete_inventory_item_without_history(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 0]);

        $response = $this->actingAs($this->superadmin())->delete("/inventory-items/{$item->id}");

        $response->assertRedirect(route('inventory-items.index'));
        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_technician_can_view_but_not_create_or_delete(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 0]);
        $technician = $this->withRole('technician');

        $this->actingAs($technician)->get('/inventory-items')->assertOk();
        $this->actingAs($technician)->get("/inventory-items/{$item->id}")->assertOk();
        $this->actingAs($technician)->get('/inventory-items/create')->assertForbidden();
        $this->actingAs($technician)->delete("/inventory-items/{$item->id}")->assertForbidden();
    }

    public function test_non_superadmin_non_technician_roles_forbidden(): void
    {
        foreach (['finance', 'customer'] as $role) {
            $this->actingAs($this->withRole($role))->get('/inventory-items')->assertForbidden();
        }
    }
}
