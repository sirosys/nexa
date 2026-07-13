<?php

namespace App\Providers;

use App\Services\Mikrotik\Drivers\LogMikrotikGateway;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Support\ServiceProvider;

class MikrotikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cuma driver 'log' untuk sekarang — pola match() dipertahankan
        // (bukan bind langsung ke LogMikrotikGateway) supaya menambah
        // driver sungguhan nanti (mis. 'rest') tidak perlu mengubah
        // provider ini, cukup tambah satu case. Lihat CLAUDE.md
        // "Integrasi MikroTik".
        $this->app->bind(MikrotikGateway::class, function () {
            return match (config('services.mikrotik.driver', 'log')) {
                default => new LogMikrotikGateway,
            };
        });
    }
}
