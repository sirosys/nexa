<?php

namespace App\Jobs;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Terpisah dari SendOtpWhatsappNotification (OTP login) - itu memanggil
 * WhatsappGateway::sendOtp() yang template pesannya hardcode menyebut
 * config('otp.ttl_minutes'), bukan config('payment_otp.ttl_minutes').
 * Job ini memanggil sendMessage() (generik) dengan teks yang sudah dirakit
 * pemanggil (App\Services\PaymentOtpService), supaya TTL yang disebut di
 * pesan selalu cocok dengan TTL OTP pembayaran yang sungguhan.
 */
class SendPaymentOtpWhatsappNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
    ) {}

    public function handle(WhatsappGateway $gateway): void
    {
        $gateway->sendMessage($this->phone, $this->message);
    }
}
