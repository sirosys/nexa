<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Raw-HTTP client for go-whatsapp-web-multidevice (README mandates raw HTTP,
 * no SDK). No real gateway server is available yet to confirm the exact
 * request/response contract, so the endpoint path/payload below are a
 * best-effort placeholder — revisit once that server is actually stood up.
 */
class HttpWhatsappGateway implements WhatsappGateway
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $token,
        private readonly ?string $sender,
    ) {}

    public function sendOtp(string $phone, string $code): bool
    {
        $message = "Kode OTP NEXA Anda: {$code}. Berlaku {$this->ttlMinutes()} menit. Jangan bagikan kode ini kepada siapa pun.";

        $response = Http::baseUrl((string) $this->baseUrl)
            ->withToken((string) $this->token)
            ->timeout(10)
            ->post('/send/message', [
                'phone' => $phone,
                'message' => $message,
                'sender' => $this->sender,
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp gateway gagal mengirim OTP', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    private function ttlMinutes(): int
    {
        return (int) config('otp.ttl_minutes');
    }
}
