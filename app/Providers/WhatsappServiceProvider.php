<?php

namespace App\Providers;

use App\Services\Whatsapp\Drivers\HttpWhatsappGateway;
use App\Services\Whatsapp\Drivers\LogWhatsappGateway;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Support\ServiceProvider;

class WhatsappServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsappGateway::class, function () {
            return match (config('services.whatsapp.driver', 'log')) {
                'http' => new HttpWhatsappGateway(
                    config('services.whatsapp.base_url'),
                    config('services.whatsapp.token'),
                    config('services.whatsapp.sender'),
                ),
                default => new LogWhatsappGateway,
            };
        });
    }
}
