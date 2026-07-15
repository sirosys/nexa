<?php

use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Hapus total produk bertipe 'langganan' — perannya sudah sepenuhnya
     * digantikan modul Plan (lihat CLAUDE.md "Plan"). Sale historis yang
     * masih membundel produk ini sebagai baris (sisa registrasi sebelum
     * modul Plan ada) di-recalculate ulang lewat SaleService supaya
     * total/grandtotal tetap benar setelah baris itu dibuang — bukan
     * dihapus mentah dengan query builder yang bisa membuat total basi.
     */
    public function up(): void
    {
        $legacyIds = Product::where('type', 'langganan')->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        $saleService = app(SaleService::class);

        $affectedSaleIds = Sale::whereHas('products', fn ($q) => $q->whereIn('products.id', $legacyIds))
            ->pluck('id');

        foreach ($affectedSaleIds as $saleId) {
            $sale = Sale::find($saleId);

            $remainingLines = $sale->products()
                ->whereNotIn('products.id', $legacyIds)
                ->get()
                ->map(fn ($product) => [
                    'product_id' => $product->id,
                    'price' => (float) $product->pivot->price,
                    'quantity' => (int) $product->pivot->quantity,
                    'discount' => (float) $product->pivot->discount,
                    'unit' => $product->pivot->unit,
                ])
                ->all();

            $saleService->syncProductsAndRecalculate($sale, $remainingLines);
        }

        Product::whereIn('id', $legacyIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak bisa direverse — data produk & baris sale_products yang
        // dihapus tidak disimpan di migration ini. Restore dari backup DB
        // kalau perlu rollback.
    }
};
