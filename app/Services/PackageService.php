<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function create(array $data): Package
    {
        return DB::transaction(function () use ($data) {
            $package = Package::create([
                'is_starter' => $data['is_starter'] ?? false,
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
            'duration_months' => $this->deriveDurationMonths($products),
        ]);
    }

    /**
     * Durasi masa aktif paket = quantity item produk bertipe 'langganan'
     * (1 unit = 1 bulan) — bukan dijumlah kalau ada lebih dari satu item
     * langganan, karena PackageRequest sudah mewajibkan quantity-nya
     * seragam. Default 1 bulan kalau paket tidak punya item langganan.
     *
     * @param  array<int, array{product_id: int, quantity: int, price: float|string}>  $products
     */
    private function deriveDurationMonths(array $products): int
    {
        $productIds = collect($products)->pluck('product_id');

        $subscriptionProductIds = Product::whereIn('id', $productIds)
            ->where('type', 'langganan')
            ->pluck('id');

        $subscriptionRow = collect($products)
            ->first(fn (array $row) => $subscriptionProductIds->contains($row['product_id']));

        return $subscriptionRow['quantity'] ?? 1;
    }
}
