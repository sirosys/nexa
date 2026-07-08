<?php

namespace App\Jobs;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOtpWhatsappNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $phone,
        public readonly string $code,
    ) {}

    public function handle(WhatsappGateway $gateway): void
    {
        $gateway->sendOtp($this->phone, $this->code);
    }
}
