<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $package = Package::findOrFail($data['package_id']);

            $isRenewal = $data['is_renewal'] ?? false;

            $sale = Sale::create([
                'service_id' => $data['service_id'],
                'package_id' => $data['package_id'],
                // Sale renewal TIDAK PERNAH is_starter, terlepas dari
                // is_starter paket registrasi yang masih terpasang di
                // service_id-nya — guard defensif, lihat CLAUDE.md "Renewal".
                'is_starter' => $isRenewal ? false : $package->is_starter,
                'is_renewal' => $isRenewal,
                'notes' => $data['notes'] ?? null,
                'tax' => $data['tax'] ?? 0,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $sale->update([
                'code' => 'SAL'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->syncProductsAndRecalculate($sale, $data['products']);

            return $sale;
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $package = Package::findOrFail($data['package_id']);

            $sale->update([
                'service_id' => $data['service_id'],
                'package_id' => $data['package_id'],
                // is_renewal bukan field yang bisa diubah lewat form edit
                // Sale — pakai nilai yang sudah ada di model, bukan $data.
                'is_starter' => $sale->is_renewal ? false : $package->is_starter,
                'notes' => $data['notes'] ?? null,
                'tax' => $data['tax'] ?? 0,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'updated_by' => Auth::id(),
            ]);

            $this->syncProductsAndRecalculate($sale, $data['products']);

            return $sale;
        });
    }

    public function delete(Sale $sale): void
    {
        $sale->delete();
    }

    /**
     * Sync line item ke pivot sale_products, lalu hitung ulang penuh
     * total/discount/subtotal/grandtotal dari baris yang baru disimpan —
     * angka ini tidak pernah dipercaya dari request client. Publik (bukan
     * private) supaya bisa dipanggil ulang dari SaleSeeder tanpa Auth::id()
     * (null di konteks console) — method ini tidak menyentuh kolom audit.
     *
     * @param  array<int, array{product_id: int, price: float|string, quantity: int, discount?: float|string|null, unit?: string|null}>  $products
     */
    public function syncProductsAndRecalculate(Sale $sale, array $products): void
    {
        $pivotData = collect($products)->mapWithKeys(fn (array $row) => [
            $row['product_id'] => [
                'price' => $row['price'],
                'discount' => $row['discount'] ?? 0,
                'quantity' => $row['quantity'],
                'unit' => $row['unit'] ?? null,
            ],
        ])->all();

        $sale->products()->sync($pivotData);

        $lines = collect($products);
        $total = $lines->sum(fn (array $row) => $row['price'] * $row['quantity']);
        $discount = $lines->sum(fn (array $row) => $row['discount'] ?? 0);
        $subtotal = $total - $discount;
        $grandtotal = $subtotal + $sale->tax + $sale->admin_fee;

        $sale->update([
            'total' => $total,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'grandtotal' => $grandtotal,
        ]);
    }
}
