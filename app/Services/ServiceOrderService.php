<?php

namespace App\Services;

use App\Models\Package;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceOrderService
{
    public function create(array $data): ServiceOrder
    {
        return DB::transaction(function () use ($data) {
            $package = Package::findOrFail($data['package_id']);

            $isRenewal = $data['is_renewal'] ?? false;

            $serviceOrder = ServiceOrder::create([
                'service_id' => $data['service_id'],
                'package_id' => $data['package_id'],
                // Plan yang ditagih di Order Layanan ini — kalau tidak
                // dikirim eksplisit (mis. RenewalService memberi harga
                // katalog SAAT INI + qty=1), derivasi diam-diam dari
                // package, pola sama is_starter di bawah (lihat CLAUDE.md
                // "Plan"). plan_price diambil dari packages.price
                // (SATU-SATUNYA acuan harga paket sejak packages.plan_price
                // dihapus, lihat CLAUDE.md "Product & Package") — sudah
                // berupa angka TOTAL untuk seluruh plan_qty (bukan tarif
                // per bulan yang perlu dikalikan lagi, lihat
                // syncProductsAndRecalculate()).
                'plan_id' => $data['plan_id'] ?? $package->plan_id,
                'plan_price' => $data['plan_price'] ?? $package->price,
                'plan_qty' => $data['plan_qty'] ?? $package->plan_qty,
                // Order Layanan renewal TIDAK PERNAH is_starter, terlepas
                // dari is_starter paket registrasi yang masih terpasang di
                // service_id-nya — guard defensif, lihat CLAUDE.md "Renewal".
                'is_starter' => $isRenewal ? false : $package->is_starter,
                'is_renewal' => $isRenewal,
                'notes' => $data['notes'] ?? null,
                'tax' => $data['tax'] ?? 0,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncProductsAndRecalculate($serviceOrder, $data['products']);

            return $serviceOrder;
        });
    }

    public function update(ServiceOrder $serviceOrder, array $data): ServiceOrder
    {
        return DB::transaction(function () use ($serviceOrder, $data) {
            $package = Package::findOrFail($data['package_id']);

            $serviceOrder->update([
                'service_id' => $data['service_id'],
                'package_id' => $data['package_id'],
                'plan_id' => $data['plan_id'] ?? $package->plan_id,
                'plan_price' => $data['plan_price'] ?? $package->price,
                'plan_qty' => $data['plan_qty'] ?? $package->plan_qty,
                // is_renewal bukan field yang bisa diubah lewat form edit
                // Order Layanan — pakai nilai yang sudah ada di model,
                // bukan $data.
                'is_starter' => $serviceOrder->is_renewal ? false : $package->is_starter,
                'notes' => $data['notes'] ?? null,
                'tax' => $data['tax'] ?? 0,
                'admin_fee' => $data['admin_fee'] ?? 0,
                'updated_by' => Auth::id(),
            ]);

            $this->syncProductsAndRecalculate($serviceOrder, $data['products']);

            return $serviceOrder;
        });
    }

    public function delete(ServiceOrder $serviceOrder): void
    {
        $serviceOrder->delete();
    }

    /**
     * Sync line item ke pivot service_order_products, lalu hitung ulang
     * penuh total/discount/subtotal/grandtotal dari baris yang baru
     * disimpan — angka ini tidak pernah dipercaya dari request client.
     * Publik (bukan private) supaya bisa dipanggil ulang dari
     * ServiceOrderSeeder tanpa Auth::id() (null di konteks console) —
     * method ini tidak menyentuh kolom audit.
     *
     * Total sekarang juga menyertakan plan_price milik Order Layanan ini
     * sendiri (sudah tersimpan sebelum method ini dipanggil, lihat
     * create()/update()) — plan bukan lagi baris service_order_products,
     * jadi tidak ikut ke $products di parameter ini. plan_price TIDAK
     * dikalikan plan_qty di sini — nilainya sudah berupa TOTAL yang mau
     * ditagih untuk seluruh durasi plan_qty (diambil dari packages.price,
     * sebuah angka flat, bukan tarif per bulan — lihat CLAUDE.md "Service
     * Order"), supaya paket seperti "6 bulan sekaligus lebih hemat" bisa
     * dihargai lebih murah dari 6× tarif bulanan, bukan otomatis dikalikan
     * qty.
     *
     * @param  array<int, array{product_id: int, price: float|string, quantity: int, discount?: float|string|null, unit?: string|null}>  $products
     */
    public function syncProductsAndRecalculate(ServiceOrder $serviceOrder, array $products): void
    {
        $pivotData = collect($products)->mapWithKeys(fn (array $row) => [
            $row['product_id'] => [
                'price' => $row['price'],
                'discount' => $row['discount'] ?? 0,
                'quantity' => $row['quantity'],
                'unit' => $row['unit'] ?? null,
            ],
        ])->all();

        $serviceOrder->products()->sync($pivotData);

        $lines = collect($products);
        $planTotal = (float) ($serviceOrder->plan_price ?? 0);
        $total = $lines->sum(fn (array $row) => $row['price'] * $row['quantity']) + $planTotal;
        $discount = $lines->sum(fn (array $row) => $row['discount'] ?? 0);
        $subtotal = $total - $discount;
        $grandtotal = $subtotal + $serviceOrder->tax + $serviceOrder->admin_fee;

        $serviceOrder->update([
            'total' => $total,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'grandtotal' => $grandtotal,
        ]);
    }
}
