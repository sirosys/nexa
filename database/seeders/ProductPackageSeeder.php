<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductPackageSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');

        $products = Product::factory(10)->create([
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        $packages = Package::factory(10)->create([
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        // Pastikan minimal separuh paket jadi starter (bisa dipilih saat
        // pendaftaran baru) supaya ServiceSeeder — yang mewajibkan
        // package_id ber-is_starter=true — selalu punya pilihan.
        $packages->take(5)->each(fn (Package $package) => $package->update(['is_starter' => true]));

        $packages->each(function (Package $package) use ($products) {
            $selected = $products->random(fake()->numberBetween(2, 4));

            foreach ($selected as $product) {
                $package->products()->attach($product->id, [
                    'quantity' => fake()->numberBetween(1, 3),
                    // Snapshot harga produk saat dibundel, independen dari
                    // products.price yang bisa berubah setelahnya.
                    'price' => $product->price,
                ]);
            }
        });
    }
}
