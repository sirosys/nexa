<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ganti konsep "base product" (produk bertipe langganan yang dibundel di
     * package_product) jadi Plan mandiri. Untuk tiap Package yang punya
     * base_product_id: buat/pakai Plan dari data Product terkait, isi
     * packages.plan_id/plan_price (snapshot package_product.price)/plan_qty
     * (dari duration_months lama), lalu unbundle baris package_product-nya —
     * plan tidak lagi direpresentasikan sebagai item bundel. Lihat CLAUDE.md
     * "Plan" & "Product & Package".
     *
     * Tidak menyentuh sale_products historis sama sekali — riwayat Sale lama
     * dibiarkan apa adanya (sudah direkonsiliasi manual untuk data dev yang
     * relevan di sesi sebelumnya).
     */
    public function up(): void
    {
        $packages = DB::table('packages')->whereNotNull('base_product_id')->get();

        // product_id lama -> plan_id baru, supaya produk yang sama dipakai
        // lebih dari satu paket tidak menghasilkan Plan duplikat.
        $planIdByProductId = [];

        foreach ($packages as $package) {
            $product = DB::table('products')->find($package->base_product_id);

            if (! $product) {
                continue;
            }

            if (! isset($planIdByProductId[$product->id])) {
                $planIdByProductId[$product->id] = DB::table('plans')->insertGetId([
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'created_by' => $product->created_by,
                    'updated_by' => $product->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $planId = $planIdByProductId[$product->id];
                DB::table('plans')->where('id', $planId)
                    ->update(['code' => 'PLN'.str_pad((string) $planId, 6, '0', STR_PAD_LEFT)]);
            }

            $pivot = DB::table('package_product')
                ->where('package_id', $package->id)
                ->where('product_id', $package->base_product_id)
                ->first();

            DB::table('packages')->where('id', $package->id)->update([
                'plan_id' => $planIdByProductId[$product->id],
                'plan_price' => $pivot->price ?? $product->price,
                'plan_qty' => $package->duration_months ?? 1,
            ]);

            DB::table('package_product')
                ->where('package_id', $package->id)
                ->where('product_id', $package->base_product_id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('packages')->update(['plan_id' => null, 'plan_price' => null, 'plan_qty' => null]);
        DB::table('plans')->truncate();
    }
};
