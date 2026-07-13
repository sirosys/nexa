<?php

namespace App\Services\Mikrotik\Drivers;

use App\Models\Pop;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Raw-HTTP client untuk RouterOS REST API (v7+) — lihat CLAUDE.md
 * "Integrasi MikroTik". BELUM PERNAH DIUJI KE ROUTER SUNGGUHAN (belum ada
 * perangkat yang sudah diupgrade ke RouterOS 7 + bisa dijangkau dari dev
 * saat driver ini ditulis) — pola sama HttpXenditGateway/
 * HttpWhatsappGateway sebelum diuji end-to-end: endpoint path & bentuk
 * payload di sini best-effort berdasarkan dokumentasi resmi MikroTik,
 * BUKAN hasil percobaan langsung. Revisit begitu ada router sungguhan.
 *
 * Kredensial & alamat koneksi diambil PER-Pop (host/api_port/api_username
 * dari kolom pops, password dari pops.token) — BUKAN dari config global
 * seperti HttpXenditGateway/HttpWhatsappGateway, karena tiap PoP adalah
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
    public function createPppoeSecret(Pop $pop, string $username, string $password, ?string $profile = null): bool
    {
        $payload = array_filter([
            'name' => $username,
            'password' => $password,
            'service' => 'pppoe',
            'profile' => $profile,
        ], fn ($value) => $value !== null);

        $response = $this->client($pop)->put('/rest/ppp/secret', $payload);

        if ($response->failed()) {
            $this->logFailure($pop, 'createPppoeSecret', $response);

            throw new RuntimeException("Gagal membuat PPPoE secret di MikroTik ({$pop->code}): HTTP {$response->status()}");
        }

        return true;
    }

    public function enablePppoeSecret(Pop $pop, string $username): bool
    {
        return $this->setDisabled($pop, $username, false);
    }

    public function disablePppoeSecret(Pop $pop, string $username): bool
    {
        return $this->setDisabled($pop, $username, true);
    }

    public function deletePppoeSecret(Pop $pop, string $username): bool
    {
        $id = $this->findSecretId($pop, $username);

        // Secret sudah tidak ada — dianggap sukses (idempotent), bukan
        // error. Pemanggil (MikrotikService::remove()) tidak perlu tahu
        // bedanya "berhasil dihapus" vs "memang sudah tidak ada".
        if ($id === null) {
            return true;
        }

        $response = $this->client($pop)->delete("/rest/ppp/secret/{$id}");

        if ($response->failed()) {
            $this->logFailure($pop, 'deletePppoeSecret', $response);

            throw new RuntimeException("Gagal menghapus PPPoE secret di MikroTik ({$pop->code}): HTTP {$response->status()}");
        }

        return true;
    }

    private function setDisabled(Pop $pop, string $username, bool $disabled): bool
    {
        $id = $this->findSecretId($pop, $username);

        if ($id === null) {
            throw new RuntimeException("PPPoE secret \"{$username}\" tidak ditemukan di MikroTik ({$pop->code}).");
        }

        $response = $this->client($pop)->patch("/rest/ppp/secret/{$id}", [
            'disabled' => $disabled ? 'true' : 'false',
        ]);

        if ($response->failed()) {
            $this->logFailure($pop, $disabled ? 'disablePppoeSecret' : 'enablePppoeSecret', $response);

            throw new RuntimeException("Gagal mengubah status PPPoE secret di MikroTik ({$pop->code}): HTTP {$response->status()}");
        }

        return true;
    }

    /**
     * Cari .id internal RouterOS untuk secret dengan nama tertentu — .id
     * (mis. "*1") dipakai RouterOS sebagai identifier di endpoint
     * PATCH/DELETE, BUKAN nama secret itu sendiri.
     */
    private function findSecretId(Pop $pop, string $username): ?string
    {
        $response = $this->client($pop)->get('/rest/ppp/secret', ['name' => $username]);

        if ($response->failed()) {
            $this->logFailure($pop, 'findSecretId', $response);

            throw new RuntimeException("Gagal mencari PPPoE secret di MikroTik ({$pop->code}): HTTP {$response->status()}");
        }

        $results = (array) $response->json();

        return $results[0]['.id'] ?? null;
    }

    private function client(Pop $pop): PendingRequest
    {
        if (blank($pop->host)) {
            throw new RuntimeException("PoP {$pop->code} belum punya host/IP router yang dikonfigurasi.");
        }

        $port = $pop->api_port ?? 80;

        return Http::baseUrl("http://{$pop->host}:{$port}")
            ->withBasicAuth((string) $pop->api_username, (string) $pop->token)
            ->acceptJson()
            ->timeout(10);
    }

    private function logFailure(Pop $pop, string $action, Response $response): void
    {
        Log::error("MikroTik REST API gagal ({$action})", [
            'pop_code' => $pop->code,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }
}
