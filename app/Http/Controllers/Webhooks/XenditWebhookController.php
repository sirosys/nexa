<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Service;
use App\Notifications\PaymentReceivedNotification;
use App\Services\NotificationService;
use App\Services\RenewalService;
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
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly RenewalService $renewalService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->header('x-callback-token');

        if (! $token || ! hash_equals((string) config('services.xendit.webhook_token'), $token)) {
            Log::warning('Xendit webhook ditolak: callback token tidak cocok');

            return response()->json(['message' => 'Invalid callback token'], 403);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? $payload;

        $paymentRequestId = $data['payment_request_id'] ?? $data['id'] ?? null;
        $status = $data['status'] ?? null;

        // NEXA cuma berlangganan event Payment Requests API v3 (lihat
        // CLAUDE.md "Billing / Invoice (Xendit)") - payment_request_id
        // dari data.payment_request_id/data.id (lihat contoh payload resmi
        // Xendit). Xendit tetap mengirim event LAIN ke webhook URL yang
        // sama (mis. tombol "Test Webhook" untuk kategori "Payment Token"
        // di dashboard, payload data.payment_token_id - tidak ada
        // payment_request_id sama sekali karena memang tidak relevan untuk
        // sistem ini). Xendit mewajibkan 2xx untuk SETIAP percobaan
        // pengiriman webhook apa pun jenisnya - non-2xx berulang membuat
        // Xendit menandai endpoint bermasalah. Jadi event yang tidak
        // relevan/tidak dikenal diabaikan diam-diam (cukup di-log), bukan
        // dianggap error request.
        if (! $paymentRequestId) {
            Log::info('Xendit webhook: event diabaikan (bukan payment request)', ['event' => $event]);

            return response()->json(['message' => 'Event tidak relevan, diabaikan']);
        }

        $receipt = Receipt::where('xendit_payment_request_id', $paymentRequestId)->first();

        if (! $receipt) {
            // Sama seperti di atas: payment_request_id yang tidak dikenal
            // (mis. dummy dari tombol "Test Webhook" kategori Payment
            // Request) tetap dijawab 200, cuma di-log, bukan diproses.
            Log::warning('Xendit webhook: receipt tidak ditemukan', ['payment_request_id' => $paymentRequestId]);

            return response()->json(['message' => 'Receipt not found, ignored']);
        }

        // Idempotency: event sukses yang sama datang lagi (Xendit retry
        // webhook) tidak mengulang efek samping (Order Layanan/Service/notifikasi).
        if ($receipt->status === 'SUCCEEDED') {
            return response()->json(['message' => 'Already processed']);
        }

        $receipt->update([
            'status' => $status ?? $receipt->status,
            'raw_response' => $payload,
        ]);

        if ($status === 'SUCCEEDED') {
            $this->markServiceOrderAsPaid($receipt);
        }

        return response()->json(['message' => 'OK']);
    }

    private function markServiceOrderAsPaid(Receipt $receipt): void
    {
        $serviceOrder = $receipt->serviceOrder()->with('service.user')->first();

        if (! $serviceOrder) {
            return;
        }

        $serviceOrder->update(['settled_at' => now()]);

        // Order Layanan renewal (lihat CLAUDE.md "Renewal") lewat jalur
        // reaktivasi, bukan jalur registrasi pending_installation —
        // RenewalService yang kirim notifikasinya sendiri
        // (ServiceReactivatedNotification).
        if ($serviceOrder->is_renewal) {
            $this->renewalService->reactivate($serviceOrder);

            return;
        }

        $service = $serviceOrder->service;

        if ($service) {
            $service->update(['status' => Service::STATUS_PENDING_INSTALLATION]);
        }

        if ($service?->user) {
            $this->notificationService->send($service->user, new PaymentReceivedNotification($serviceOrder));
        }
    }
}
