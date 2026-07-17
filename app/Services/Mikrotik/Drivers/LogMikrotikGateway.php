<?php

namespace App\Services\Mikrotik\Drivers;

use App\Models\Site;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Support\Facades\Log;

/**
 * Dev-only driver: belum ada router MikroTik sungguhan yang bisa diakses
 * dari environment dev, dan protokol koneksi (REST v7+/API klasik/SSH)
 * belum diputuskan — lihat CLAUDE.md "Integrasi MikroTik". Menulis niat
 * aksi ke log (bukan benar-benar konek ke router), pola sama persis
 * LogWhatsappGateway sebelum go-whatsapp-web-multidevice sungguhan ada.
 */
class LogMikrotikGateway implements MikrotikGateway
{
    public function createPppoeSecret(Site $site, string $username, string $password, ?string $profile = null): bool
    {
        $this->log('createPppoeSecret', $site, ['username' => $username, 'profile' => $profile]);

        return true;
    }

    public function enablePppoeSecret(Site $site, string $username): bool
    {
        $this->log('enablePppoeSecret', $site, ['username' => $username]);

        return true;
    }

    public function disablePppoeSecret(Site $site, string $username): bool
    {
        $this->log('disablePppoeSecret', $site, ['username' => $username]);

        return true;
    }

    public function deletePppoeSecret(Site $site, string $username): bool
    {
        $this->log('deletePppoeSecret', $site, ['username' => $username]);

        return true;
    }

    public function isReachable(Site $site): bool
    {
        $this->log('isReachable', $site, []);

        return true;
    }

    private function log(string $action, Site $site, array $context): void
    {
        Log::channel(config('services.mikrotik.log_channel', 'stack'))->info(
            "[MikroTik {$action} - LOG DRIVER] aksi tidak benar-benar dikirim ke router",
            array_merge(['site_code' => $site->code, 'site_name' => $site->name], $context)
        );
    }
}
