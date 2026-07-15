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
                'valid_until' => $data['valid_until'] ?? null,
                'plan_id' => $data['plan_id'],
                'plan_price' => $data['plan_price'],
                'plan_qty' => $data['plan_qty'],
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
                'valid_until' => $data['valid_until'] ?? null,
                'plan_id' => $data['plan_id'],
                'plan_price' => $data['plan_price'],
                'plan_qty' => $data['plan_qty'],
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
    }
}
