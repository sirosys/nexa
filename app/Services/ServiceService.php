<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Product;
use App\Models\Service;
use App\Notifications\ServiceRegisteredNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly ReceiptService $receiptService,
        private readonly NotificationService $notificationService,
    ) {}

    public function create(array $data): Service
    {
        $sale = DB::transaction(function () use ($data) {
            $service = Service::create([
                'pin' => $this->generatePin(),
                'user_id' => $data['user_id'],
                'address' => $data['address'],
                'residential_name' => $data['residential_name'] ?? null,
                'subdistrict_id' => $data['subdistrict_id'],
                'rw' => $data['rw'] ?? null,
                'rt' => $data['rt'] ?? null,
                'coverage_id' => $data['coverage_id'],
                'package_id' => $data['package_id'],
                'status' => Service::STATUS_PENDING_PAYMENT,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $service->update([
                'code' => 'SRV'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT),
            ]);

            // Sale (tagihan pendaftaran) untuk paket starter yang dipilih
            // dibuat otomatis di sini — staff tidak input manual ke /sales
            // terpisah untuk pendaftaran awal (lihat CLAUDE.md "Service").
            $package = Package::with('products')->findOrFail($data['package_id']);

            $sale = $this->saleService->create([
                'service_id' => $service->id,
                'package_id' => $package->id,
                'products' => $this->buildProductLines($package),
            ]);

            return $sale->setRelation('service', $service);
        });

        $this->notificationService->send($sale->service->user, new ServiceRegisteredNotification($sale->service));

        // Sengaja di luar transaksi Service+Sale di atas: ini panggilan
        // HTTP eksternal ke Xendit (lihat CLAUDE.md "Billing / Invoice
        // (Xendit)") — tidak boleh menahan lock DB kalau lambat/gagal.
        // ReceiptService yang mengirim notifikasi tagihan (kalau berhasil
        // dibuat) — bukan di sini, supaya retry manual lewat
        // SaleController::retryReceipt() juga ikut mengirim notifikasi.
        $this->receiptService->createForSale($sale);

        return $sale->service;
    }

    public function update(Service $service, array $data): Service
    {
        $service->update([
            'pin' => $data['pin'] ?? $service->pin,
            'user_id' => $data['user_id'],
            'address' => $data['address'],
            'residential_name' => $data['residential_name'] ?? null,
            'subdistrict_id' => $data['subdistrict_id'],
            'rw' => $data['rw'] ?? null,
            'rt' => $data['rt'] ?? null,
            'coverage_id' => $data['coverage_id'],
            'package_id' => $data['package_id'],
            'updated_by' => Auth::id(),
        ]);

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Baris produk untuk Sale pendaftaran awal, disalin dari snapshot
     * package_product paket starter yang dipilih (quantity/price sama
     * seperti yang tersimpan di paket, bukan harga produk terkini).
     *
     * @return array<int, array{product_id: int, price: float, quantity: int, unit: ?string}>
     */
    private function buildProductLines(Package $package): array
    {
        return $package->products->map(fn (Product $product) => [
            'product_id' => $product->id,
            'price' => (float) $product->pivot->price,
            'quantity' => (int) $product->pivot->quantity,
            'unit' => $product->unit,
        ])->values()->all();
    }
}
