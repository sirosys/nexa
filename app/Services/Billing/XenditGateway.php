<?php

namespace App\Services\Billing;

interface XenditGateway
{
    /**
     * Buat Payment Request baru di Xendit untuk satu tagihan.
     *
     * @param  string  $referenceId  Idempotency key sisi kita (Receipt::code)
     *                               — dikirim sebagai reference_id supaya
     *                               retry dengan reference_id yang sama
     *                               tidak dobel charge di sisi Xendit.
     * @param  array<int, string>  $enabledMethods  Tipe metode pembayaran
     *                                              Xendit yang diaktifkan.
     * @return array{id: ?string, status: string, checkout_url: ?string, raw: array<string, mixed>}
     *
     * @throws \RuntimeException Kalau panggilan ke Xendit gagal (HTTP error,
     *                           network, dll) — pemanggil (ReceiptService)
     *                           bertanggung jawab menangkap ini supaya tidak
     *                           menjalar ke alur pembuatan Service/Sale.
     */
    public function createPaymentRequest(string $referenceId, float $amount, string $description, array $enabledMethods): array;
}
