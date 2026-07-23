<?php

namespace App\Services\Billing;

interface XenditGateway
{
    /**
     * Buat Payment Request baru di Xendit untuk satu channel pembayaran
     * spesifik yang sudah dipilih pelanggan (lihat CLAUDE.md "Billing /
     * Invoice (Xendit)" - Payment Requests API v3 tidak punya halaman
     * checkout hosted multi-channel, satu request cuma untuk satu channel).
     *
     * @param  string  $referenceId  Idempotency key sisi kita (Receipt::code)
     *                               — dikirim sebagai reference_id supaya
     *                               retry dengan reference_id yang sama
     *                               tidak dobel charge di sisi Xendit.
     * @param  string  $channelCode  Channel Xendit spesifik (mis.
     *                               "BCA_VIRTUAL_ACCOUNT", "QRIS", "ALFAMART").
     * @param  array<string, mixed>  $channelProperties  Data spesifik channel
     *                                                   (mis. payer_name untuk
     *                                                   retail) — lihat
     *                                                   ReceiptService::buildChannelProperties().
     * @param  string  $type  Tipe Payment Request Xendit - "PAY" (sekali
     *                        pakai, dipakai QRIS/retail) atau
     *                        "REUSABLE_PAYMENT_CODE" (dipakai berulang
     *                        sampai kadaluarsa, wajib untuk Virtual Account
     *                        — lihat ReceiptService::resolveRequestType()).
     * @return array{id: ?string, status: string, checkout_url: ?string, actions: array<int, array<string, mixed>>, raw: array<string, mixed>}
     *
     * @throws \RuntimeException Kalau panggilan ke Xendit gagal (HTTP error,
     *                           network, dll) — pemanggil (ReceiptService)
     *                           bertanggung jawab menangkap ini supaya tidak
     *                           menjalar ke alur pembuatan Service/Order Layanan.
     */
    public function createPaymentRequest(string $referenceId, float $amount, string $description, string $channelCode, array $channelProperties, string $type = 'PAY'): array;
}
