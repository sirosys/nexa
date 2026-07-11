<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->input('phone'));
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Satu limiter untuk seluruh aksi POST /pay/{receipt} (kirim ulang
        // OTP, verifikasi OTP, pilih channel) - route-nya disatukan (lihat
        // docblock PaymentController::update()), jadi tidak bisa dipisah
        // per-aksi seperti otp-request/otp-verify di atas. Cukup longgar
        // utk pemakaian wajar (verifikasi + pilih channel dlm satu sesi),
        // tapi menahan percobaan brute-force kode OTP lewat network.
        RateLimiter::for('payment-action', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.$request->route('receipt'));
        });
    }
}
