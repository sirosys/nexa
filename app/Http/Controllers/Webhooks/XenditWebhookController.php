<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Service;
use App\Notifications\PaymentReceivedNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Menerima callback pembayaran dari Xendit (Payment Requests API v3).
 * Bentuk pasti payload event belum diverifikasi ke sandbox sungguhan —
 * ekstraksi id/status di bawah sengaja defensif (coba beberapa kemungkinan
 * bentuk), revisit begitu diuji end-to-end dengan kredensial dev.
 */
class XenditWebhookController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->header('x-callback-token');

        if (! $token || ! hash_equals((string) config('services.xendit.webhook_token'), $token)) {
            Log::warning('Xendit webhook ditolak: callback token tidak cocok');

            return response()->json(['message' => 'Invalid callback token'], 403);
        }

        $payload = $request->all();
        $data = $payload['data'] ?? $payload;

        $paymentRequestId = $data['payment_request_id'] ?? $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (! $paymentRequestId) {
            return response()->json(['message' => 'Payload tidak berisi payment request id'], 422);
        }

        $receipt = Receipt::where('xendit_payment_request_id', $paymentRequestId)->first();

        if (! $receipt) {
            Log::warning('Xendit webhook: receipt tidak ditemukan', ['payment_request_id' => $paymentRequestId]);

            return response()->json(['message' => 'Receipt not found'], 404);
        }

        // Idempotency: event sukses yang sama datang lagi (Xendit retry
        // webhook) tidak mengulang efek samping (Sale/Service/notifikasi).
        if ($receipt->status === 'SUCCEEDED') {
            return response()->json(['message' => 'Already processed']);
        }

        $receipt->update([
            'status' => $status ?? $receipt->status,
            'raw_response' => $payload,
        ]);

        if ($status === 'SUCCEEDED') {
            $this->markSaleAsPaid($receipt);
        }

        return response()->json(['message' => 'OK']);
    }

    private function markSaleAsPaid(Receipt $receipt): void
    {
        $sale = $receipt->sale()->with('service.user')->first();

        if (! $sale) {
            return;
        }

        $sale->update(['settled_at' => now()]);

        $service = $sale->service;

        if ($service) {
            $service->update(['status' => Service::STATUS_PENDING_INSTALLATION]);
        }

        if ($service?->user) {
            $this->notificationService->send($service->user, new PaymentReceivedNotification($sale));
        }
    }
}
