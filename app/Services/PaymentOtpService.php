<?php

namespace App\Services;

use App\Exceptions\OtpThrottledException;
use App\Jobs\SendPaymentOtpWhatsappNotification;
use App\Models\Receipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi identitas pelanggan yang membuka link publik /pay/{receipt}
 * lewat kode 6 digit WhatsApp - TERPISAH dari App\Services\OtpService
 * (OTP login). Alasan tidak reuse OtpService (lihat CLAUDE.md "Billing /
 * Invoice (Xendit)"): otp_codes.user_id NOT NULL terikat ke User (bukan
 * Receipt), OtpService::verify() punya efek samping hardcode
 * `$user->update(['last_login_at' => now()])` yang salah secara semantik
 * di sini, dan state OTP login disimpan di session yang tidak relevan
 * untuk rute publik signed-URL ini.
 */
class PaymentOtpService
{
    public function send(Receipt $receipt): void
    {
        if (Cache::has($this->cooldownKey($receipt))) {
            throw new OtpThrottledException;
        }

        $code = $this->generateCode();
        $phone = (string) $receipt->sale->service->user->phone;

        DB::transaction(function () use ($receipt, $code) {
            $receipt->otpCodes()->valid()->update(['consumed_at' => now()]);

            $receipt->otpCodes()->create([
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes((int) config('payment_otp.ttl_minutes')),
                'attempts' => 0,
            ]);
        });

        Cache::put($this->cooldownKey($receipt), true, now()->addSeconds((int) config('payment_otp.resend_cooldown_seconds')));

        $message = "[NEXA] Kode verifikasi pembayaran Anda: {$code}. Berlaku ".config('payment_otp.ttl_minutes').' menit. Jangan bagikan kode ini ke siapa pun.';

        SendPaymentOtpWhatsappNotification::dispatch($phone, $message)
            ->onConnection(config('payment_otp.queue_connection'));
    }

    public function verify(Receipt $receipt, string $code): bool
    {
        return DB::transaction(function () use ($receipt, $code) {
            $otp = $receipt->otpCodes()->valid()->latest()->lockForUpdate()->first();

            if (! $otp || $otp->attempts >= (int) config('payment_otp.max_attempts')) {
                return false;
            }

            if (! hash_equals($otp->code_hash, $this->hash($code))) {
                $otp->increment('attempts');

                return false;
            }

            $otp->update(['consumed_at' => now()]);

            return true;
        });
    }

    /**
     * Tidak berbasis session - supaya tetap valid meski dibuka dari
     * device/browser berbeda dari saat pelanggan minta kode.
     */
    public function isVerified(Receipt $receipt): bool
    {
        return $receipt->otpCodes()
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '>=', now()->subMinutes((int) config('payment_otp.verified_grace_minutes')))
            ->exists();
    }

    /**
     * Dipakai controller untuk memutuskan perlu auto-kirim kode baru atau
     * tidak saat halaman pertama kali dibuka (tidak kirim ulang kalau
     * masih ada kode valid yang belum kadaluarsa/dipakai).
     */
    public function hasPendingCode(Receipt $receipt): bool
    {
        return $receipt->otpCodes()->valid()->exists();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    private function cooldownKey(Receipt $receipt): string
    {
        return "payment-otp:resend:{$receipt->id}";
    }
}
