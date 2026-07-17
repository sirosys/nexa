<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Site;
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
            $site = $this->siteFor($service);

            return $this->gateway->createPppoeSecret($site, $service->code, $service->pin);
        });
    }

    public function enable(Service $service): void
    {
        $this->safeCall('enable', $service, function () use ($service) {
            return $this->gateway->enablePppoeSecret($this->siteFor($service), $service->code);
        });
    }

    public function disable(Service $service): void
    {
        $this->safeCall('disable', $service, function () use ($service) {
            return $this->gateway->disablePppoeSecret($this->siteFor($service), $service->code);
        });
    }

    public function remove(Service $service): void
    {
        $this->safeCall('remove', $service, function () use ($service) {
            return $this->gateway->deletePppoeSecret($this->siteFor($service), $service->code);
        });
    }

    /**
     * Dipakai command monitoring:check-site-status (lihat CLAUDE.md
     * "Monitoring") — beda dari provision/enable/disable/remove di atas,
     * method ini punya nilai balik yang dipakai pemanggil (bukan cuma
     * efek samping), jadi kegagalan gateway ditelan jadi `false`, bukan
     * dilempar/diabaikan diam-diam.
     */
    public function checkStatus(Site $site): bool
    {
        try {
            return $this->gateway->isReachable($site);
        } catch (Throwable $exception) {
            Log::warning("MikroTik checkStatus gagal untuk Site {$site->code}", [
                'site_id' => $site->id,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function siteFor(Service $service): Site
    {
        $service->loadMissing('coverage.site');

        return $service->coverage->site;
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
