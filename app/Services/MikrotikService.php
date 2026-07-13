<?php

namespace App\Services;

use App\Models\Pop;
use App\Models\Service;
use App\Services\Mikrotik\MikrotikGateway;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wrapper bisnis di atas MikrotikGateway — satu-satunya cara modul lain
 * seharusnya provisioning/enable/disable/hapus PPPoE secret (bukan panggil
 * MikrotikGateway langsung), pola sama persis NotificationService::send().
 * Kegagalan gateway (network, auth, router down, dll) SELALU ditelan
 * jadi Log::warning, tidak pernah menjalar ke pemanggil — kegagalan
 * integrasi jaringan tidak boleh menggagalkan alur bisnis inti (aktivasi,
 * suspend, reaktivasi, dismantle Service tetap tercatat benar di NEXA
 * meski PPPoE secret di router gagal disinkronkan). Lihat CLAUDE.md
 * "Integrasi MikroTik".
 *
 * PPPoE username = Service::code, password = Service::pin (keduanya
 * sudah ada & auto-generate sejak modul Service) — tidak ada profile
 * bandwidth per paket di iterasi ini (belum ada kolom itu di packages).
 */
class MikrotikService
{
    public function __construct(private readonly MikrotikGateway $gateway) {}

    public function provision(Service $service): void
    {
        $this->safeCall('provision', $service, function () use ($service) {
            $pop = $this->popFor($service);

            return $this->gateway->createPppoeSecret($pop, $service->code, $service->pin);
        });
    }

    public function enable(Service $service): void
    {
        $this->safeCall('enable', $service, function () use ($service) {
            return $this->gateway->enablePppoeSecret($this->popFor($service), $service->code);
        });
    }

    public function disable(Service $service): void
    {
        $this->safeCall('disable', $service, function () use ($service) {
            return $this->gateway->disablePppoeSecret($this->popFor($service), $service->code);
        });
    }

    public function remove(Service $service): void
    {
        $this->safeCall('remove', $service, function () use ($service) {
            return $this->gateway->deletePppoeSecret($this->popFor($service), $service->code);
        });
    }

    private function popFor(Service $service): Pop
    {
        $service->loadMissing('coverage.pop');

        return $service->coverage->pop;
    }

    private function safeCall(string $action, Service $service, Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning("MikroTik {$action} gagal untuk service {$service->code}", [
                'service_id' => $service->id,
                'action' => $action,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
