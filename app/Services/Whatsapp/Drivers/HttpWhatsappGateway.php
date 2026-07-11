<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Raw-HTTP client for go-whatsapp-web-multidevice (README mandates raw HTTP,
 * no SDK). Server ini diautentikasi lewat HTTP Basic Auth (username/password
 * yang dikonfigurasi di sisi server gateway), bukan Bearer token — beda dari
 * kebanyakan REST API lain. Endpoint path/payload di bawah masih best-effort
 * (belum ada dokumentasi resmi yang dirujuk), revisit begitu diuji end-to-end
 * dengan kredensial gateway sungguhan.
 */
class HttpWhatsappGateway implements WhatsappGateway
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $username,
        private readonly ?string $password,
    ) {}

    public function sendOtp(string $phone, string $code): bool
    {
        $message = "Kode OTP NEXA Anda: {$code}. Berlaku {$this->ttlMinutes()} menit. Jangan bagikan kode ini kepada siapa pun.";

        $response = Http::baseUrl((string) $this->baseUrl)
            ->withBasicAuth((string) $this->username, (string) $this->password)
            ->timeout(10)
            ->post('/send/message', [
                'phone' => $phone,
                'message' => $message,
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

    public function sendMessage(string $phone, string $message): bool
    {
        $response = Http::baseUrl((string) $this->baseUrl)
            ->withBasicAuth((string) $this->username, (string) $this->password)
            ->timeout(10)
            ->post('/send/message', [
                'phone' => $phone,
                'message' => $message,
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp gateway gagal mengirim pesan', [
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
