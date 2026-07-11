<?php

namespace App\Services\Whatsapp;

interface WhatsappGateway
{
    /**
     * Send an OTP code to the given phone number via WhatsApp.
     * Returns true if the message was accepted for delivery.
     */
    public function sendOtp(string $phone, string $code): bool;

    /**
     * Send an arbitrary text message via WhatsApp (used by generic
     * notifications, not tied to OTP's hardcoded template).
     * Returns true if the message was accepted for delivery.
     */
    public function sendMessage(string $phone, string $message): bool;
}
