<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductPackageSeeder extends Seeder
{
    /**
     * Data produk & paket meniru demo data ISP dari app sebelumnya
     * (~/Webapp/xnet/app/database/seeders/{Product,Package}Seeder.php) —
     * nama/harga/bundling nyata, bukan fake() acak — supaya demo NEXA
     * punya konteks bisnis yang masuk akal.
     *
     * Dibuat lewat Eloquent langsung (bukan ProductService/PackageService)
     * karena Auth::id() null di konteks console — pola sama seperti
     * SaleSeeder. `code` digenerate manual setelah insert supaya tetap
     * mengikuti konvensi PRD/PKG + id yang dipakai service layer asli.
     */
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');

        $productDefinitions = [
            'net_basic' => ['type' => 'langganan', 'name' => 'Internet Basic 10 Mbps', 'description' => 'Kecepatan download 10 Mbps / upload 5 Mbps.', 'price' => 100000, 'unit' => 'bulan'],
            'net_std' => ['type' => 'langganan', 'name' => 'Internet Standar 20 Mbps', 'description' => 'Kecepatan download 20 Mbps / upload 10 Mbps.', 'price' => 150000, 'unit' => 'bulan'],
            'net_prm' => ['type' => 'langganan', 'name' => 'Internet Premium 50 Mbps', 'description' => 'Kecepatan download 50 Mbps / upload 25 Mbps.', 'price' => 250000, 'unit' => 'bulan'],
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
                'base_product' => 'net_basic',
                'items' => [
                    ['product' => 'net_basic', 'quantity' => 1, 'price' => 0],
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                'name' => 'Paket Standar 20 Mbps',
                'description' => 'Kecepatan download 20 Mbps / upload 10 Mbps. Ideal untuk streaming HD dan kerja dari rumah.',
                'price' => 150000,
                'is_starter' => true,
                'base_product' => 'net_std',
                'items' => [
                    ['product' => 'net_std', 'quantity' => 1, 'price' => 0],
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                'name' => 'Paket Premium 50 Mbps',
                'description' => 'Kecepatan download 50 Mbps / upload 25 Mbps. Cocok untuk streaming 4K, gaming online, dan banyak perangkat.',
                'price' => 250000,
                'is_starter' => true,
                'base_product' => 'net_prm',
                'items' => [
                    ['product' => 'net_prm', 'quantity' => 1, 'price' => 0],
                    ['product' => 'install', 'quantity' => 1, 'price' => 150000],
                    ['product' => 'modem_prm', 'quantity' => 1, 'price' => 0],
                ],
            ],
            [
                // Paket renewal — TIDAK starter, karena cuma boleh dipilih
                // pelanggan yang sudah aktif (bayar 6 bulan sekaligus),
                // bukan saat pendaftaran baru. Renewal OTOMATIS (H-5) tidak
                // memakai paket ini sama sekali (selalu 1 bulan langsung
                // dari base_product, lihat RenewalService) — paket ini
                // disiapkan untuk jalur perpanjangan non-default pelanggan
                // lewat customer app/API di masa depan (lihat CLAUDE.md
                // "Renewal").
                'name' => 'Paket Basic 10 Mbps — Semesteran',
                'description' => 'Paket Basic 10 Mbps dibayar 6 bulan sekaligus — lebih hemat dibanding bulanan.',
                'price' => 450000,
                'is_starter' => false,
                'base_product' => 'net_basic',
                'items' => [
                    ['product' => 'net_basic', 'quantity' => 6, 'price' => 0],
                ],
            ],
            [
                'name' => 'Promo Tahun Ajaran Baru',
                'description' => 'Internet 10 Mbps 3 bulan pertama + gratis biaya pasang & modem. Khusus pendaftaran baru.',
                'price' => 250000,
                'is_starter' => true,
                'base_product' => 'net_basic',
                'items' => [
                    ['product' => 'net_basic', 'quantity' => 3, 'price' => 0],
                    ['product' => 'install', 'quantity' => 1, 'price' => 0],
                    ['product' => 'modem_std', 'quantity' => 1, 'price' => 0],
                ],
            ],
        ];

        foreach ($packageDefinitions as $data) {
            $package = Package::create([
                'is_starter' => $data['is_starter'],
                'base_product_id' => $products[$data['base_product']]->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $package->update(['code' => 'PKG'.str_pad((string) $package->id, 6, '0', STR_PAD_LEFT)]);

            $subscriptionQuantity = 1;

            foreach ($data['items'] as $item) {
                $product = $products[$item['product']];

                $package->products()->attach($product->id, [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                if ($item['product'] === $data['base_product']) {
                    $subscriptionQuantity = $item['quantity'];
                }
            }

            // duration_months normalnya dihitung PackageService::deriveDurationMonths()
            // saat create()/update() lewat form — seeder pakai attach() langsung,
            // jadi dihitung manual di sini dengan logika yang sama (quantity
            // baris base_product).
            $package->update(['duration_months' => $subscriptionQuantity]);
        }
    }
}
