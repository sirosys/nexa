<?php

namespace Tests\Support;

use App\Services\Whatsapp\WhatsappGateway;

class CapturingWhatsappGateway implements WhatsappGateway
{
    public ?string $phone = null;

    public ?string $code = null;

    public ?string $message = null;

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

        return true;
    }
}
