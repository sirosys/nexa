<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Support\Facades\Log;

/**
 * Dev-only driver: no real WhatsApp gateway server (go-whatsapp-web-multidevice)
 * is available yet. Writes the OTP to the log so it's visible locally instead
 * of failing silently or requiring a live gateway to test the login flow.
 */
class LogWhatsappGateway implements WhatsappGateway
{
    public function sendOtp(string $phone, string $code): bool
    {
        // Sama persis template pesan HttpWhatsappGateway::sendOtp(), supaya
        // staff bisa review kata-kata yang sebenarnya dikirim lewat log,
        // bukan cuma kode mentahnya.
        $message = "Kode OTP NEXA Anda: {$code}. Berlaku ".config('otp.ttl_minutes').' menit. Jangan bagikan kode ini kepada siapa pun.';

        Log::channel(config('services.whatsapp.log_channel', 'stack'))->info(
            '[WhatsApp OTP - LOG DRIVER] pesan tidak benar-benar terkirim',
            ['phone' => $phone, 'code' => $code, 'message' => $message]
        );

        return true;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        Log::channel(config('services.whatsapp.log_channel', 'stack'))->info(
            '[WhatsApp Message - LOG DRIVER] pesan tidak benar-benar terkirim',
            ['phone' => $phone, 'message' => $message]
        );

        return true;
    }
}
