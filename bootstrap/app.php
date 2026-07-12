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
        // project ini (lihat CLAUDE.md "Billing / Invoice (Xendit)").
        $schedule->command('billing:cancel-expired-invoices')->hourly();

        // Modul Renewal (lihat CLAUDE.md "Renewal") — granularitas harian,
        // reminder/suspend adalah konsep per-hari bukan per-jam. Urutan
        // create → remind → suspend sengaja dijalankan berurutan (staggered
        // beberapa menit) walau masing-masing independen, supaya log cron
        // lebih mudah dibaca.
        $schedule->command('renewal:create-invoices')->dailyAt('06:00');
        $schedule->command('renewal:send-reminders')->dailyAt('06:05');
        $schedule->command('renewal:suspend-overdue')->dailyAt('06:10');

        // Modul Dismantle (lihat CLAUDE.md "Dismantle") — menutup siklus
        // hidup Service, dijalankan setelah renewal:suspend-overdue supaya
        // Service yang baru saja disuspend hari ini tidak langsung
        // terantre di run yang sama (mereka baru eligible setelah ambang
        // config(dismantle.suspended_months_threshold) lewat).
        $schedule->command('dismantle:queue-overdue-suspensions')->dailyAt('06:15');
    })
    ->create();
