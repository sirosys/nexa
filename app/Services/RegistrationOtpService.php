<?php

namespace App\Services;

use App\Exceptions\OtpThrottledException;
use App\Jobs\SendOtpWhatsappNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Verifikasi kepemilikan nomor telepon SEBELUM akun `User` dibuat — dipakai
 * alur registrasi mandiri lewat API customer-facing (lihat CLAUDE.md "API
 * Customer-Facing"). BEDA dari App\Services\OtpService: OtpService terikat
 * ke `User` yang sudah ada (`otp_codes.user_id` NOT NULL), sedangkan di sini
 * belum ada baris User sama sekali — state-nya murni di Cache (pola sama
 * `verification_token` di OtpController::request(), bukan tabel baru).
 */
class RegistrationOtpService
{
    public function requestOtp(string $phone): string
    {
        if (Cache::has($this->cooldownKey($phone))) {
            throw new OtpThrottledException;
        }

        $code = $this->generateCode();
        $token = Str::random(64);

        Cache::put($this->challengeKey($token), [
            'phone' => $phone,
            'code_hash' => $this->hash($code),
            'attempts' => 0,
        ], now()->addMinutes((int) config('registration_otp.ttl_minutes')));

        Cache::put($this->cooldownKey($phone), true, now()->addSeconds((int) config('registration_otp.resend_cooldown_seconds')));

        SendOtpWhatsappNotification::dispatch($phone, $code)
            ->onConnection(config('registration_otp.queue_connection'));

        return $token;
    }

    /**
     * Sukses → nomor telepon yang baru saja diverifikasi (dipakai pemanggil
     * untuk membuat `User`). Gagal → null. Token SELALU di-forget setelah
     * percobaan sukses (sekali pakai) — percobaan gagal TIDAK forget token,
     * supaya pelanggan masih bisa mencoba lagi sampai `max_attempts`.
     */
    public function verify(string $token, string $code): ?string
    {
        $challenge = Cache::get($this->challengeKey($token));

        if (! $challenge || $challenge['attempts'] >= (int) config('registration_otp.max_attempts')) {
            return null;
        }

        if (! hash_equals($challenge['code_hash'], $this->hash($code))) {
            $challenge['attempts']++;
            Cache::put($this->challengeKey($token), $challenge, now()->addMinutes((int) config('registration_otp.ttl_minutes')));

            return null;
        }

        Cache::forget($this->challengeKey($token));

        return $challenge['phone'];
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    private function challengeKey(string $token): string
    {
        return "registration_otp:challenge:{$token}";
    }

    private function cooldownKey(string $phone): string
    {
        return "registration_otp:resend:{$phone}";
    }
}
