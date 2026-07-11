<?php

namespace App\Services\Billing\Drivers;

use App\Services\Billing\XenditGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Raw-HTTP client untuk Xendit Payment Requests API v3 (CLAUDE.md "Payment
 * Gateway (Xendit)" mewajibkan raw HTTP, bukan SDK resmi). Setiap Payment
 * Request wajib menyebut satu `channel_code` spesifik - API ini TIDAK
 * punya halaman checkout hosted multi-channel (dikonfirmasi lewat
 * percobaan langsung ke sandbox: request tanpa channel_code ditolak
 * "Either channel_code or payment_token_id is required"), makanya
 * pemilihan channel dipindah ke halaman /pay/{receipt} milik NEXA sendiri
 * (lihat ReceiptService::selectChannel()). Bentuk `channel_properties`
 * per kategori channel best-effort (Xendit mengarahkan ke "Channel Data
 * Finder" widget interaktif yang tidak terdokumentasi statis) - revisit
 * begitu diuji ke sandbox sungguhan.
 */
class HttpXenditGateway implements XenditGateway
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $secretKey,
    ) {}

    public function createPaymentRequest(string $referenceId, float $amount, string $description, string $channelCode, array $channelProperties, string $type = 'PAY'): array
    {
        $response = Http::baseUrl((string) $this->baseUrl)
            ->withBasicAuth((string) $this->secretKey, '')
            ->withHeaders(['api-version' => '2024-11-11'])
            ->timeout(15)
            ->post('/v3/payment_requests', [
                'reference_id' => $referenceId,
                'type' => $type,
                'country' => 'ID',
                'currency' => 'IDR',
                'request_amount' => $amount,
                'description' => $description,
                'capture_method' => 'AUTOMATIC',
                'channel_code' => $channelCode,
                'channel_properties' => (object) $channelProperties,
            ]);

        if ($response->failed()) {
            Log::error('Xendit gagal membuat payment request', [
                'reference_id' => $referenceId,
                'channel_code' => $channelCode,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Xendit payment request gagal dibuat: HTTP {$response->status()}");
        }

        $body = $response->json() ?? [];
        $actions = (array) ($body['actions'] ?? []);

        return [
            // Response body Payment Requests v3 memakai field
            // "payment_request_id" - dikonfirmasi lewat percobaan
            // sungguhan ke sandbox (bukan "id" seperti dugaan awal, yang
            // membuat receipts.xendit_payment_request_id selalu ter-null
            // walau request-nya sukses).
            'id' => $body['payment_request_id'] ?? null,
            'status' => $body['status'] ?? 'PENDING',
            'checkout_url' => $this->extractRedirectUrl($actions),
            'actions' => $actions,
            'raw' => $body,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function extractRedirectUrl(array $actions): ?string
    {
        foreach ($actions as $action) {
            if (($action['type'] ?? null) === 'REDIRECT_CUSTOMER' && ($action['descriptor'] ?? null) === 'WEB_URL') {
                return $action['value'] ?? null;
            }
        }

        return null;
    }
}
