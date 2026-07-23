<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Product;
use App\Models\Service;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ServiceRegisteredNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
        private readonly ReceiptService $receiptService,
        private readonly NotificationService $notificationService,
    ) {}

    public function create(array $data): Service
    {
        $serviceOrder = DB::transaction(function () use ($data) {
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

            // `code` sudah digenerate otomatis lewat Service::booted() —
            // tidak perlu diisi di sini (lihat CLAUDE.md "Service").

            // Order Layanan (tagihan pendaftaran) untuk paket starter yang
            // dipilih dibuat otomatis di sini — staff tidak input manual ke
            // /service-orders terpisah untuk pendaftaran awal (lihat
            // CLAUDE.md "Service").
            $package = Package::with('products')->findOrFail($data['package_id']);

            $serviceOrder = $this->serviceOrderService->create([
                'service_id' => $service->id,
                'package_id' => $package->id,
                'products' => $this->buildProductLines($package),
            ]);

            return $serviceOrder->setRelation('service', $service);
        });

        $this->notificationService->send($serviceOrder->service->user, new ServiceRegisteredNotification($serviceOrder->service));

        // Sengaja di luar transaksi Service+Order Layanan di atas: ini
        // panggilan HTTP eksternal ke Xendit (lihat CLAUDE.md "Billing /
        // Invoice (Xendit)") — tidak boleh menahan lock DB kalau
        // lambat/gagal. ReceiptService yang mengirim notifikasi tagihan
        // (kalau berhasil dibuat) — bukan di sini, supaya retry manual
        // lewat ServiceOrderController::retryReceipt() juga ikut mengirim
        // notifikasi.
        $this->receiptService->createForServiceOrder($serviceOrder);

        // Paket gratis (grandtotal 0, mis. promo) membuat
        // ReceiptService::createForServiceOrder() langsung mengisi
        // service_order.settled_at TANPA pernah membuat Receipt — tidak ada
        // webhook Xendit yang bisa memicu transisi
        // pending_payment -> pending_installation seperti pembayaran
        // sungguhan, jadi Service akan macet selamanya di
        // pending_payment kalau tidak ditangani eksplisit di sini
        // (pola sama RenewalService::createInvoiceForDueService() yang
        // mendeteksi kasus sama untuk Order Layanan perpanjangan).
        if ($serviceOrder->fresh()->settled_at !== null) {
            $serviceOrder->service->update(['status' => Service::STATUS_PENDING_INSTALLATION]);
            $this->notificationService->send($serviceOrder->service->user, new PaymentReceivedNotification($serviceOrder));
        }

        return $serviceOrder->service;
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
     * Baris produk untuk Order Layanan pendaftaran awal, disalin dari snapshot
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
