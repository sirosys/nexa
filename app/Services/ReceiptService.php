<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\Sale;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Billing\XenditGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReceiptService
{
    public function __construct(
        private readonly XenditGateway $gateway,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Buat tagihan Xendit untuk sebuah Sale. Kalau grandtotal 0 (paket
     * promo gratis), skip Xendit sepenuhnya dan langsung tandai lunas —
     * tidak ada gunanya membuat Payment Request untuk Rp 0.
     *
     * Panggilan HTTP ke Xendit sengaja TIDAK dibungkus DB::transaction()
     * oleh method ini (dan harus dipanggil di luar transaksi Service+Sale
     * oleh pemanggil) supaya request eksternal yang lambat/gagal tidak
     * menahan lock database.
     */
    public function createForSale(Sale $sale): ?Receipt
    {
        if ((float) $sale->grandtotal <= 0) {
            $sale->update(['settled_at' => now()]);

            return null;
        }

        $receipt = $sale->receipt ?? Receipt::create([
            'sale_id' => $sale->id,
            'amount' => $sale->grandtotal,
            'status' => 'PENDING',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (! $receipt->code) {
            $receipt->update([
                'code' => 'REC'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        try {
            $result = $this->gateway->createPaymentRequest(
                referenceId: $receipt->code,
                amount: (float) $sale->grandtotal,
                description: "Tagihan pendaftaran {$sale->code}",
                enabledMethods: config('billing.payment_methods'),
            );
        } catch (Throwable $exception) {
            Log::error('Gagal membuat Xendit payment request untuk Sale', [
                'sale_id' => $sale->id,
                'receipt_id' => $receipt->id,
                'exception' => $exception->getMessage(),
            ]);

            return $receipt;
        }

        $receipt->update([
            'xendit_payment_request_id' => $result['id'],
            'status' => $result['status'],
            'checkout_url' => $result['checkout_url'],
            'raw_response' => $result['raw'],
            'updated_by' => Auth::id(),
        ]);

        $sale->update([
            'invoiced_at' => now(),
            'expired_at' => now()->addDays((int) config('billing.invoice_ttl_days')),
        ]);

        $this->notificationService->send($sale->service->user, new InvoiceCreatedNotification($receipt));

        return $receipt;
    }
}
