<?php

namespace App\Providers;

use App\Services\Mikrotik\Drivers\HttpMikrotikGateway;
use App\Services\Mikrotik\Drivers\LogMikrotikGateway;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Support\ServiceProvider;

class MikrotikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 'http' (HttpMikrotikGateway, RouterOS REST API v7+) BELUM PERNAH
        // diuji ke router sungguhan — lihat CLAUDE.md "Integrasi MikroTik".
        // Default tetap 'log' sampai ada perangkat untuk divalidasi;
        // kredensial/alamat koneksi tidak dibaca dari config di sini sama
        // sekali karena per-Site (lihat HttpMikrotikGateway), bukan global
        // seperti WhatsApp/Xendit.
        $this->app->bind(MikrotikGateway::class, function () {
            return match (config('services.mikrotik.driver', 'log')) {
                'http' => new HttpMikrotikGateway,
                default => new LogMikrotikGateway,
            };
        });
    }
}
