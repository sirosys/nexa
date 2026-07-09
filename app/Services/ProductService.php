<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'unit' => $data['unit'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $product->update([
                'code' => 'PRD'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        $product->update([
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'unit' => $data['unit'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
