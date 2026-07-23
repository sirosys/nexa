<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductPackageSeeder extends Seeder
{
    /**
     * Data plan/produk/paket meniru demo data ISP dari app sebelumnya
     * (~/Webapp/xnet/app/database/seeders/{Product,Package}Seeder.php) —
     * nama/harga/bundling nyata, bukan fake() acak — supaya demo NEXA
     * punya konteks bisnis yang masuk akal.
     *
     * Dibuat lewat Eloquent langsung (bukan Product/Package/PlanService)
     * karena Auth::id() null di konteks console — pola sama seperti
     * ServiceOrderSeeder. `code` digenerate manual setelah insert supaya tetap
     * mengikuti konvensi PLN/PRD/PKG + id yang dipakai service layer asli.
     */
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');

        $planDefinitions = [
            'net_basic' => ['name' => 'Internet Basic 10 Mbps', 'description' => 'Kecepatan download 10 Mbps / upload 5 Mbps.', 'price' => 100000],
            'net_std' => ['name' => 'Internet Standar 20 Mbps', 'description' => 'Kecepatan download 20 Mbps / upload 10 Mbps.', 'price' => 150000],
            'net_prm' => ['name' => 'Internet Premium 50 Mbps', 'description' => 'Kecepatan download 50 Mbps / upload 25 Mbps.', 'price' => 250000],
        ];

        $plans = collect($planDefinitions)->map(function (array $data) use ($adminId) {
            $plan = Plan::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $plan->update(['code' => 'PLN'.str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT)]);

            return $plan;
        });

        $productDefinitions = [
            'install' => ['type' => 'jasa', 'name' => 'Biaya Instalasi', 'description' => 'Jasa pemasangan layanan internet baru (penarikan kabel & konfigurasi).', 'price' => 150000, 'unit' => 'kali'],
            'modem_std' => ['type' => 'perangkat', 'name' => 'Modem Standar', 'description' => 'ONT/modem WiFi standar bawaan layanan.', 'price' => 300000, 'unit' => 'unit'],
            'modem_prm' => ['type' => 'perangkat', 'name' => 'Modem Premium', 'description' => 'ONT/modem WiFi dual-band dengan jangkauan lebih luas.', 'price' => 500000, 'unit' => 'unit'],
            'mesh' => ['type' => 'perangkat', 'name' => 'Mesh WiFi', 'description' => 'Unit mesh WiFi tambahan untuk memperluas jangkauan.', 'price' => 450000, 'unit' => 'unit'],
        ];

        $products = collect($productDefinitions)->map(function (array $data) use ($adminId) {
            $product = Product::create([
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'unit' => $data['unit'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $product->update(['code' => 'PRD'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT)]);

            return $product;
        });

        $packageDefinitions = [
            [
                'name' => 'Paket Basic 10 Mbps',
                'description' => 'Kecepatan download 10 Mbps / upload 5 Mbps. Cocok untuk penggunaan ringan seperti browsing dan media sosial.',
                'price' => 100000,
                'is_starter' => true,
                'plan' => 'net_basic',
                'plan_qty' => 1,
                'items' => [
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                'name' => 'Paket Standar 20 Mbps',
                'description' => 'Kecepatan download 20 Mbps / upload 10 Mbps. Ideal untuk streaming HD dan kerja dari rumah.',
                'price' => 150000,
                'is_starter' => true,
                'plan' => 'net_std',
                'plan_qty' => 1,
                'items' => [
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                'name' => 'Paket Premium 50 Mbps',
                'description' => 'Kecepatan download 50 Mbps / upload 25 Mbps. Cocok untuk streaming 4K, gaming online, dan banyak perangkat.',
                'price' => 250000,
                'is_starter' => true,
                'plan' => 'net_prm',
                'plan_qty' => 1,
                'items' => [
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_prm', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                // Paket renewal — TIDAK starter, karena cuma boleh dipilih
                // pelanggan yang sudah aktif (bayar 6 bulan sekaligus),
                // bukan saat pendaftaran baru. Renewal OTOMATIS (H-5) tidak
                // memakai paket ini sama sekali (selalu 1 bulan langsung
                // dari Plan, lihat RenewalService) — paket ini disiapkan
                // untuk jalur perpanjangan non-default pelanggan lewat
                // customer app/API di masa depan (lihat CLAUDE.md "Renewal").
                'name' => 'Paket Basic 10 Mbps — Semesteran',
                'description' => 'Paket Basic 10 Mbps dibayar 6 bulan sekaligus — lebih hemat dibanding bulanan.',
                'price' => 450000,
                'is_starter' => false,
                'plan' => 'net_basic',
                'plan_qty' => 6,
                'items' => [],
            ],
            [
                'name' => 'Promo Tahun Ajaran Baru',
                'description' => 'Internet 10 Mbps 3 bulan pertama + gratis biaya pasang & modem. Khusus pendaftaran baru.',
                'price' => 250000,
                'is_starter' => true,
                'plan' => 'net_basic',
                'plan_qty' => 3,
                'items' => [
                    ['product' => 'install', 'quantity' => 1, 'price' => 0],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
        ];

        foreach ($packageDefinitions as $data) {
            $package = Package::create([
                'is_starter' => $data['is_starter'],
                'plan_id' => $plans[$data['plan']]->id,
                'plan_qty' => $data['plan_qty'],
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $package->update(['code' => 'PKG'.str_pad((string) $package->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product']];

                $package->products()->attach($product->id, [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }
        }
    }
}
