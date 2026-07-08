<?php

namespace App\Services;

use App\Exceptions\OtpThrottledException;
use App\Jobs\SendOtpWhatsappNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OtpService
{
    public function requestOtp(User $user): void
    {
        if (Cache::has($this->cooldownKey($user))) {
            throw new OtpThrottledException;
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($user, $code) {
            $user->otpCodes()->valid()->update(['consumed_at' => now()]);

            $user->otpCodes()->create([
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
                'attempts' => 0,
            ]);
        });

        Cache::put($this->cooldownKey($user), true, now()->addSeconds((int) config('otp.resend_cooldown_seconds')));

        SendOtpWhatsappNotification::dispatch($user->phone, $code)
            ->onConnection(config('otp.queue_connection'));
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $otp = $user->otpCodes()->valid()->latest()->lockForUpdate()->first();

            if (! $otp || $otp->attempts >= (int) config('otp.max_attempts')) {
                return false;
            }

            if (! hash_equals($otp->code_hash, $this->hash($code))) {
                $otp->increment('attempts');

                return false;
            }

            $otp->update(['consumed_at' => now()]);
            $user->update(['last_login_at' => now()]);

            return true;
        });
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    private function cooldownKey(User $user): string
    {
        return "otp:resend:{$user->id}";
    }
}
