<?php

namespace App\Services\Mikrotik\Drivers;

use App\Models\Site;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Raw-HTTP client untuk RouterOS REST API (v7+) — lihat CLAUDE.md
 * "Integrasi MikroTik". BELUM PERNAH DIUJI KE ROUTER SUNGGUHAN (belum ada
 * perangkat yang sudah diupgrade ke RouterOS 7 + bisa dijangkau dari dev
 * saat driver ini ditulis) — pola sama HttpXenditGateway/
 * HttpWhatsappGateway sebelum diuji end-to-end: endpoint path & bentuk
 * payload di sini best-effort berdasarkan dokumentasi resmi MikroTik,
 * BUKAN hasil percobaan langsung. Revisit begitu ada router sungguhan.
 *
 * Kredensial & alamat koneksi diambil PER-Site (host/api_port/api_username
 * dari kolom sites, password dari sites.token) — BUKAN dari config global
 * seperti HttpXenditGateway/HttpWhatsappGateway, karena tiap Site adalah
 * router yang berbeda.
 *
 * Skema URL diasumsikan HTTP polos (bukan HTTPS) — keputusan sadar:
 * koneksi ke router direncanakan lewat tunnel WireGuard (lihat CLAUDE.md),
 * yang sudah terenkripsi end-to-end, jadi TLS tambahan di layer REST API
 * tidak wajib. Kalau nanti mau defense-in-depth (HTTPS + self-signed
 * cert), perlu kolom baru (mis. `api_use_ssl`) — sengaja belum ditambah
 * sekarang, spekulatif sebelum dikonfirmasi kebutuhannya.
 */
class HttpMikrotikGateway implements MikrotikGateway
{
    public function createPppoeSecret(Site $site, string $username, string $password, ?string $profile = null): bool
    {
        $payload = array_filter([
            'name' => $username,
            'password' => $password,
            'service' => 'pppoe',
            'profile' => $profile,
        ], fn ($value) => $value !== null);

        $response = $this->client($site)->put('/rest/ppp/secret', $payload);

        if ($response->failed()) {
            $this->logFailure($site, 'createPppoeSecret', $response);

            throw new RuntimeException("Gagal membuat PPPoE secret di MikroTik ({$site->code}): HTTP {$response->status()}");
        }

        return true;
    }

    public function enablePppoeSecret(Site $site, string $username): bool
    {
        return $this->setDisabled($site, $username, false);
    }

    public function disablePppoeSecret(Site $site, string $username): bool
    {
        return $this->setDisabled($site, $username, true);
    }

    public function deletePppoeSecret(Site $site, string $username): bool
    {
        $id = $this->findSecretId($site, $username);

        // Secret sudah tidak ada — dianggap sukses (idempotent), bukan
        // error. Pemanggil (MikrotikService::remove()) tidak perlu tahu
        // bedanya "berhasil dihapus" vs "memang sudah tidak ada".
        if ($id === null) {
            return true;
        }

        $response = $this->client($site)->delete("/rest/ppp/secret/{$id}");

        if ($response->failed()) {
            $this->logFailure($site, 'deletePppoeSecret', $response);

            throw new RuntimeException("Gagal menghapus PPPoE secret di MikroTik ({$site->code}): HTTP {$response->status()}");
        }

        return true;
    }

    /**
     * Beda kontrak dari method lain di kelas ini — tidak pernah throw,
     * kegagalan apa pun (host kosong, network, auth, router down) cukup
     * jadi false. Endpoint `/rest/system/resource` dipilih karena ringan
     * (info resource router) dan tidak mengubah state apa pun di router,
     * cocok untuk polling berkala.
     */
    public function isReachable(Site $site): bool
    {
        if (blank($site->host)) {
            return false;
        }

        try {
            return $this->client($site)->get('/rest/system/resource')->successful();
        } catch (Throwable $exception) {
            Log::warning("MikroTik isReachable gagal ({$site->code})", ['exception' => $exception->getMessage()]);

            return false;
        }
    }

    private function setDisabled(Site $site, string $username, bool $disabled): bool
    {
        $id = $this->findSecretId($site, $username);

        if ($id === null) {
            throw new RuntimeException("PPPoE secret \"{$username}\" tidak ditemukan di MikroTik ({$site->code}).");
        }

        $response = $this->client($site)->patch("/rest/ppp/secret/{$id}", [
            'disabled' => $disabled ? 'true' : 'false',
        ]);

        if ($response->failed()) {
            $this->logFailure($site, $disabled ? 'disablePppoeSecret' : 'enablePppoeSecret', $response);

            throw new RuntimeException("Gagal mengubah status PPPoE secret di MikroTik ({$site->code}): HTTP {$response->status()}");
        }

        return true;
    }

    /**
     * Cari .id internal RouterOS untuk secret dengan nama tertentu — .id
     * (mis. "*1") dipakai RouterOS sebagai identifier di endpoint
     * PATCH/DELETE, BUKAN nama secret itu sendiri.
     */
    private function findSecretId(Site $site, string $username): ?string
    {
        $response = $this->client($site)->get('/rest/ppp/secret', ['name' => $username]);

        if ($response->failed()) {
            $this->logFailure($site, 'findSecretId', $response);

            throw new RuntimeException("Gagal mencari PPPoE secret di MikroTik ({$site->code}): HTTP {$response->status()}");
        }

        $results = (array) $response->json();

        return $results[0]['.id'] ?? null;
    }

    private function client(Site $site): PendingRequest
    {
        if (blank($site->host)) {
            throw new RuntimeException("Site {$site->code} belum punya host/IP router yang dikonfigurasi.");
        }

        $port = $site->api_port ?? 80;

        return Http::baseUrl("http://{$site->host}:{$port}")
            ->withBasicAuth((string) $site->api_username, (string) $site->token)
            ->acceptJson()
            ->timeout(10);
    }

    private function logFailure(Site $site, string $action, Response $response): void
    {
        Log::error("MikroTik REST API gagal ({$action})", [
            'site_code' => $site->code,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }
}
