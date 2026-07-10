<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');
        $services = Service::inRandomOrder()->limit(10)->get();
        $packages = Package::all();
        $products = Product::all();
        $saleService = app(SaleService::class);

        $services->each(function (Service $service) use ($adminId, $packages, $products, $saleService) {
            $package = $packages->random();

            // Sale::factory()->create() dulu (bukan lewat SaleService::create()
            // — Auth::id() null di konteks console), lalu pakai
            // syncProductsAndRecalculate() supaya totalnya konsisten dengan
            // rumus asli, bukan angka acak. is_starter di-set manual di sini
            // karena bukan lewat SaleService::create() yang biasanya
            // menyalinnya otomatis dari package.
            $sale = Sale::factory()->create([
                'service_id' => $service->id,
                'package_id' => $package->id,
                'is_starter' => $package->is_starter,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $lines = $products->random(fake()->numberBetween(2, 3))->map(fn (Product $product) => [
                'product_id' => $product->id,
                'quantity' => fake()->numberBetween(1, 3),
                'price' => (float) $product->price,
                'discount' => fake()->boolean(30) ? fake()->randomFloat(2, 1000, 20000) : 0,
                'unit' => $product->unit,
            ])->values()->all();

            $saleService->syncProductsAndRecalculate($sale, $lines);
        });
    }
}
