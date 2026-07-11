<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        // Webhook Xendit dipanggil server-ke-server, tidak pernah membawa
        // CSRF token browser — verifikasi keasliannya lewat header
        // x-callback-token di XenditWebhookController, bukan CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/xendit']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `|| $request->expectsJson()` sengaja ditambahkan (bukan cuma
        // `api/*`) — endpoint AJAX admin di luar /api (mis. modal "Tambah
        // Pelanggan Baru"/"Lengkapi NIK & Foto KTP" di form Service, lihat
        // CLAUDE.md "Service") juga butuh error validasi sebagai JSON 422,
        // bukan redirect HTML. Tanpa ini, fetch() dari modal menerima
        // halaman redirect, bukan pesan error yang bisa ditampilkan.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Granularitas jatuh tempo invoice 3 hari — hourly lebih dari
        // cukup, tidak perlu lebih sering. Ini scheduler pertama di
        // project ini (lihat CLAUDE.md "Billing / Invoice (Xendit)");
        // modul Renewal/reminder nanti tinggal menambah command baru di
        // sini, bukan membangun infrastruktur scheduler dari nol lagi.
        $schedule->command('billing:cancel-expired-invoices')->hourly();
    })
    ->create();
