<?php

namespace Tests\Support;

use App\Services\Whatsapp\WhatsappGateway;

class CapturingWhatsappGateway implements WhatsappGateway
{
    public ?string $phone = null;

    public ?string $code = null;

    public ?string $message = null;

    /**
     * Riwayat lengkap seluruh sendMessage() (beda dari $phone/$message di
     * atas yang cuma menyimpan panggilan TERAKHIR) — dibutuhkan begitu
     * satu alur mengirim lebih dari satu notifikasi WhatsApp berurutan
     * (mis. ServiceRegisteredNotification lalu PaymentReceivedNotification
     * saat registrasi paket gratis) dan test perlu memeriksa pesan yang
     * bukan yang terakhir.
     *
     * @var list<array{phone: string, message: string}>
     */
    public array $messages = [];

    public function sendOtp(string $phone, string $code): bool
    {
        $this->phone = $phone;
        $this->code = $code;

        return true;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->messages[] = ['phone' => $phone, 'message' => $message];

        return true;
    }
}
