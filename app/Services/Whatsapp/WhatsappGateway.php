<?php

namespace App\Services\Whatsapp;

interface WhatsappGateway
{
    /**
     * Send an OTP code to the given phone number via WhatsApp.
     * Returns true if the message was accepted for delivery.
     */
    public function sendOtp(string $phone, string $code): bool;
}
