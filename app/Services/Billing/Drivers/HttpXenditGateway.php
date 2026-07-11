<?php

namespace App\Services\Billing\Drivers;

use App\Services\Billing\XenditGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Raw-HTTP client untuk Xendit Payment Requests API v3 (CLAUDE.md "Payment
 * Gateway (Xendit)" mewajibkan raw HTTP, bukan SDK resmi). Bentuk pasti
 * payload `payment_method` untuk mengaktifkan beberapa channel sekaligus
 * (VA/QRIS/EWALLET/OTC) dalam satu request belum diverifikasi end-to-end
 * ke sandbox sungguhan — payload di bawah best-effort dari dokumentasi
 * publik Payment Requests API v3, revisit begitu diuji dengan kredensial
 * dev yang sungguhan.
 */
class HttpXenditGateway implements XenditGateway
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $secretKey,
    ) {}

    public function createPaymentRequest(string $referenceId, float $amount, string $description, array $enabledMethods): array
    {
        $response = Http::baseUrl((string) $this->baseUrl)
            ->withBasicAuth((string) $this->secretKey, '')
            ->timeout(15)
            ->post('/v3/payment_requests', [
                'reference_id' => $referenceId,
                'currency' => 'IDR',
                'amount' => $amount,
                'description' => $description,
                'capture_method' => 'AUTOMATIC',
                'payment_method' => [
                    'reusability' => 'ONE_TIME_USE',
                    'allowed_types' => $enabledMethods,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Xendit gagal membuat payment request', [
                'reference_id' => $referenceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Xendit payment request gagal dibuat: HTTP {$response->status()}");
        }

        $body = $response->json() ?? [];

        return [
            'id' => $body['id'] ?? null,
            'status' => $body['status'] ?? 'PENDING',
            'checkout_url' => $this->extractCheckoutUrl($body),
            'raw' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractCheckoutUrl(array $body): ?string
    {
        foreach ((array) ($body['actions'] ?? []) as $action) {
            if (is_array($action) && ! empty($action['url'])) {
                return $action['url'];
            }
        }

        return null;
    }
}
