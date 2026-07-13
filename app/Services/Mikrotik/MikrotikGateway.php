<?php

namespace App\Services\Mikrotik;

use App\Models\Pop;

/**
 * Adapter untuk provisioning PPPoE secret di router MikroTik milik sebuah
 * PoP — lihat CLAUDE.md "Integrasi MikroTik". Pola driver/adapter sama
 * seperti WhatsappGateway/XenditGateway: interface di sini, driver
 * sungguhan (belum ada — cuma driver 'log' untuk sekarang) di
 * app/Services/Mikrotik/Drivers/.
 *
 * $pop dikirim ke setiap method (bukan cuma host/kredensial mentah) supaya
 * driver 'log' bisa mencatat PoP/router mana yang dituju TANPA interface
 * ini terikat ke bentuk kredensial koneksi tertentu — begitu driver
 * sungguhan dibangun (butuh kolom alamat jaringan baru di pops, belum ada
 * sama sekali saat ini), signature method di sini tidak perlu berubah.
 */
interface MikrotikGateway
{
    /**
     * Buat PPPoE secret baru di router milik $pop.
     *
     * @throws \RuntimeException Kalau panggilan ke router gagal (network,
     *                           auth, dll) — pemanggil (MikrotikService)
     *                           bertanggung jawab menangkap ini supaya
     *                           kegagalan integrasi jaringan tidak
     *                           menggagalkan alur bisnis (aktivasi Service,
     *                           dst).
     */
    public function createPppoeSecret(Pop $pop, string $username, string $password, ?string $profile = null): bool;

    /**
     * Aktifkan kembali PPPoE secret yang sempat dinonaktifkan (mis. setelah
     * pelanggan bayar tagihan yang sempat telat).
     */
    public function enablePppoeSecret(Pop $pop, string $username): bool;

    /**
     * Nonaktifkan PPPoE secret (mis. Service disuspend karena telat bayar)
     * — TANPA menghapusnya, beda dari deletePppoeSecret().
     */
    public function disablePppoeSecret(Pop $pop, string $username): bool;

    /**
     * Hapus PPPoE secret sepenuhnya (mis. Service sudah dibongkar/dismantle).
     */
    public function deletePppoeSecret(Pop $pop, string $username): bool;
}
