<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /**
     * @param  array{product_id: int, is_serialized: bool}  $data
     */
    public function createItem(array $data): InventoryItem
    {
        return DB::transaction(function () use ($data) {
            $item = InventoryItem::create([
                'product_id' => $data['product_id'],
                'is_serialized' => $data['is_serialized'],
                'quantity' => 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $item->update(['code' => 'INV'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT)]);

            return $item;
        });
    }

    public function delete(InventoryItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  array{quantity: ?int, serial_numbers: ?string, notes: ?string, purchase_order_id?: ?int}  $data
     */
    public function stockIn(InventoryItem $item, array $data): void
    {
        DB::transaction(function () use ($item, $data) {
            $purchaseOrderId = $data['purchase_order_id'] ?? null;

            if ($item->is_serialized) {
                $serials = $this->parseSerialNumbers($data['serial_numbers'] ?? '');

                foreach ($serials as $serial) {
                    $unit = InventoryUnit::create([
                        'inventory_item_id' => $item->id,
                        'serial_number' => $serial,
                        'status' => InventoryUnit::STATUS_IN_STOCK,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);

                    $this->recordMovement($item, InventoryMovement::TYPE_IN, 1, $data['notes'] ?? null, $unit->id, null, $purchaseOrderId);
                }

                $item->increment('quantity', count($serials));

                return;
            }

            $quantity = (int) $data['quantity'];
            $this->recordMovement($item, InventoryMovement::TYPE_IN, $quantity, $data['notes'] ?? null, null, null, $purchaseOrderId);
            $item->increment('quantity', $quantity);
        });
    }

    /**
     * Penyesuaian manual (mis. barang rusak/hilang) — cuma untuk item
     * non-serialized. Item serialized disesuaikan lewat perubahan status
     * unit individual, bukan angka kuantitas mentah.
     */
    public function adjustStock(InventoryItem $item, int $delta, string $notes): void
    {
        if ($item->is_serialized) {
            throw new RuntimeException('Item bertipe serial tidak bisa disesuaikan lewat kuantitas — kelola per unit.');
        }

        if ($delta < 0 && $item->quantity + $delta < 0) {
            throw new RuntimeException('Penyesuaian akan membuat stok menjadi negatif.');
        }

        DB::transaction(function () use ($item, $delta, $notes) {
            $this->recordMovement($item, InventoryMovement::TYPE_ADJUSTMENT, $delta, $notes);
            $item->increment('quantity', $delta);
        });
    }

    /**
     * Dipanggil dari InstallationService::complete() — kurangi stok untuk
     * equipment yang dipakai teknisi memasang sebuah Service. Lihat
     * CLAUDE.md "Inventaris".
     *
     * @param  array<int, array{inventory_item_id: int, quantity: ?int, serial_number: ?string}>  $usage
     */
    public function consumeForInstallation(Service $service, array $usage): void
    {
        DB::transaction(function () use ($service, $usage) {
            foreach ($usage as $row) {
                $item = InventoryItem::findOrFail($row['inventory_item_id']);

                if ($item->is_serialized) {
                    $this->consumeSerializedUnit($item, $service, (string) $row['serial_number']);
                } else {
                    $this->consumeQuantity($item, $service, (int) $row['quantity']);
                }
            }
        });
    }

    private function consumeSerializedUnit(InventoryItem $item, Service $service, string $serialNumber): void
    {
        $unit = InventoryUnit::where('inventory_item_id', $item->id)
            ->where('serial_number', $serialNumber)
            ->where('status', InventoryUnit::STATUS_IN_STOCK)
            ->first();

        if ($unit === null) {
            throw new RuntimeException("Unit dengan serial number \"{$serialNumber}\" tidak tersedia di stok.");
        }

        $unit->update([
            'status' => InventoryUnit::STATUS_INSTALLED,
            'service_id' => $service->id,
            'updated_by' => Auth::id(),
        ]);

        $this->recordMovement($item, InventoryMovement::TYPE_OUT, -1, null, $unit->id, $service->id);
        $item->decrement('quantity', 1);
    }

    private function consumeQuantity(InventoryItem $item, Service $service, int $quantity): void
    {
        if ($quantity < 1) {
            throw new RuntimeException('Kuantitas yang dipakai harus lebih dari 0.');
        }

        if ($item->quantity < $quantity) {
            throw new RuntimeException("Stok \"{$item->product?->name}\" tidak cukup (tersedia {$item->quantity}, diminta {$quantity}).");
        }

        $this->recordMovement($item, InventoryMovement::TYPE_OUT, -$quantity, null, null, $service->id);
        $item->decrement('quantity', $quantity);
    }

    private function recordMovement(InventoryItem $item, string $type, int $quantity, ?string $notes, ?int $unitId = null, ?int $serviceId = null, ?int $purchaseOrderId = null): void
    {
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'inventory_unit_id' => $unitId,
            'type' => $type,
            'quantity' => $quantity,
            'service_id' => $serviceId,
            'purchase_order_id' => $purchaseOrderId,
            'notes' => $notes,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parseSerialNumbers(string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->unique()
            ->values()
            ->all();
    }
}
