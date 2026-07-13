<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function create(array $data): Package
    {
        return DB::transaction(function () use ($data) {
            $package = Package::create([
                'is_starter' => $data['is_starter'] ?? false,
                'base_product_id' => $data['base_product_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $package->update([
                'code' => 'PKG'.str_pad((string) $package->id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->syncProducts($package, $data['products']);

            return $package;
        });
    }

    public function update(Package $package, array $data): Package
    {
        return DB::transaction(function () use ($package, $data) {
            $package->update([
                'is_starter' => $data['is_starter'] ?? false,
                'base_product_id' => $data['base_product_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'updated_by' => Auth::id(),
            ]);

            $this->syncProducts($package, $data['products']);

            return $package;
        });
    }

    public function delete(Package $package): void
    {
        $package->delete();
    }

    private function syncProducts(Package $package, array $products): void
    {
        $pivotData = collect($products)->mapWithKeys(fn (array $row) => [
            $row['product_id'] => [
                'quantity' => $row['quantity'],
                'price' => $row['price'],
            ],
        ])->all();

        $package->products()->sync($pivotData);

        $package->update([
            'duration_months' => $this->deriveDurationMonths($products, $package->base_product_id),
        ]);
    }

    /**
     * Durasi masa aktif paket = quantity baris base_product_id di paket ini
     * (1 unit = 1 bulan) — dibaca dari baris yang product_id-nya cocok
     * base_product_id (bukan lagi "first langganan match"), karena
     * base_product_id sekarang eksplisit menandai produk langganan mana
     * yang jadi acuan tier paket ini (lihat CLAUDE.md "Product & Package").
     * Default 1 bulan kalau baris tidak ketemu (seharusnya tidak pernah
     * terjadi — PackageRequest::guardBaseProduct() sudah menegakkan
     * base_product_id ada di antara $products).
     *
     * @param  array<int, array{product_id: int, quantity: int, price: float|string}>  $products
     */
    private function deriveDurationMonths(array $products, ?int $baseProductId): int
    {
        $row = collect($products)->first(fn (array $row) => (int) $row['product_id'] === $baseProductId);

        return $row['quantity'] ?? 1;
    }
}
