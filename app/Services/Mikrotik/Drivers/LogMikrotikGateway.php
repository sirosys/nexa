<?php

namespace App\Services\Mikrotik\Drivers;

use App\Models\Pop;
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
    public function createPppoeSecret(Pop $pop, string $username, string $password, ?string $profile = null): bool
    {
        $this->log('createPppoeSecret', $pop, ['username' => $username, 'profile' => $profile]);

        return true;
    }

    public function enablePppoeSecret(Pop $pop, string $username): bool
    {
        $this->log('enablePppoeSecret', $pop, ['username' => $username]);

        return true;
    }

    public function disablePppoeSecret(Pop $pop, string $username): bool
    {
        $this->log('disablePppoeSecret', $pop, ['username' => $username]);

        return true;
    }

    public function deletePppoeSecret(Pop $pop, string $username): bool
    {
        $this->log('deletePppoeSecret', $pop, ['username' => $username]);

        return true;
    }

    private function log(string $action, Pop $pop, array $context): void
    {
        Log::channel(config('services.mikrotik.log_channel', 'stack'))->info(
            "[MikroTik {$action} - LOG DRIVER] aksi tidak benar-benar dikirim ke router",
            array_merge(['pop_code' => $pop->code, 'pop_name' => $pop->name], $context)
        );
    }
}
