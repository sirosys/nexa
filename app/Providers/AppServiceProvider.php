<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        // Spatie\Permission\Models\Role bukan model App\Models\*, jadi
        // konvensi auto-discovery Policy bawaan Laravel (menebak
        // App\Policies\{Basename}Policy berdasar namespace model) tidak
        // pernah menemukan RolePolicy secara otomatis — harus didaftarkan
        // eksplisit di sini. Lihat CLAUDE.md "Authorization / Role &
        // Permission" (modul Role & Permission Management, /roles).
        Gate::policy(Role::class, RolePolicy::class);

        RateLimiter::for('otp-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->input('phone'));
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Bucket terpisah dari otp-request/otp-verify (admin) — API
        // customer-facing /api/v1 punya alur OTP sendiri (lihat CLAUDE.md
        // "API Customer-Facing"), limit sama tapi diisolasi supaya trafik
        // salah satu sisi tidak ikut menghabiskan kuota sisi lain.
        RateLimiter::for('api-otp-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->input('phone'));
        });

        RateLimiter::for('api-otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Registrasi mandiri pelanggan (lihat CLAUDE.md "API
        // Customer-Facing" — RegistrationOtpService) — bucket terpisah lagi
        // dari api-otp-request/api-otp-verify (itu untuk LOGIN nomor yang
        // sudah terdaftar, ini untuk memverifikasi nomor BARU sebelum akun
        // dibuat).
        RateLimiter::for('api-register-otp-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->input('phone'));
        });

        RateLimiter::for('api-register', function (Request $request) {
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
