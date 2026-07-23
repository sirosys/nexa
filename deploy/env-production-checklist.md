# Checklist `.env` Production — NEXA

Bukan file `.env` siap pakai — ini daftar item yang WAJIB diverifikasi/diubah
sebelum go-live, disusun dari hasil review kesiapan deployment (2026-07-23).
`.env` tidak pernah di-commit ke repo, jadi checklist ini yang jadi acuan.

## Wajib diubah dari nilai dev

- [ ] `APP_ENV=production` (bukan `local`)
- [ ] `APP_DEBUG=false` (bukan `true` — kalau dibiarkan `true`, stack trace
      lengkap termasuk query DB & path server bocor ke browser publik)
- [ ] `APP_URL=https://...` — domain final production, skema **https**
      (dibutuhkan Xendit webhook & Laravel signed URL `/pay/{receipt}` supaya
      link yang dikirim ke pelanggan valid)
- [ ] `WHATSAPP_GATEWAY_DRIVER=http` (bukan `log`) — kalau dibiarkan `log`,
      SEMUA notifikasi WhatsApp (OTP login, OTP pembayaran, notifikasi
      bisnis) cuma tertulis ke log, tidak pernah benar-benar terkirim ke
      pelanggan/staff. Pastikan `WA_GATEWAY_URL`/`WA_GATEWAY_USERNAME`/
      `WA_GATEWAY_PASSWORD` mengarah ke server gateway production yang
      sudah diverifikasi bisa kirim pesan sungguhan.
- [ ] `XENDIT_SECRET_KEY` — **harus** key production (prefix `xnd_production_...`),
      BUKAN key sandbox (`xnd_development_...`). **Wajib dicek berpasangan**
      dengan `XENDIT_BASE_URL` — kombinasi base_url production + secret key
      sandbox (atau sebaliknya) akan menyebabkan transaksi gagal/salah
      lingkungan secara diam-diam.
- [ ] `XENDIT_WEBHOOK_TOKEN` — token webhook sungguhan dari dashboard Xendit
      production (dipasang di pengaturan webhook Xendit, dicocokkan di
      `XenditWebhookController::handle()` lewat `hash_equals`).
- [ ] `QUEUE_CONNECTION=database` — sudah benar di dev, cuma pastikan ikut
      terbawa ke `.env` production (BUKAN `sync`, yang akan memblokir
      request sampai job selesai, tidak cocok untuk volume production).

## Wajib jalan sebagai proses/infra, bukan cuma nilai `.env`

- [ ] Queue worker daemon aktif — pasang `deploy/supervisor-nexa-queue.conf`
      (lihat file itu untuk instruksi), BUKAN `php artisan queue:listen`
      (pola dev, mati kalau terminal/session ditutup).
- [ ] Cron scheduler aktif — pasang entry di `deploy/crontab-nexa`, BUKAN
      `php artisan schedule:work` (pola dev). Tanpa ini, seluruh job
      terjadwal (`billing:cancel-expired-invoices`, `renewal:*`,
      `dismantle:queue-overdue-suspensions`, `monitoring:check-site-status`)
      TIDAK PERNAH JALAN — tagihan kadaluarsa tidak pernah dibatalkan,
      reminder H-5/H-3/H-1 tidak pernah terkirim, auto-suspend/dismantle
      tidak pernah terjadi.
- [ ] Webhook Xendit (`POST /webhooks/xendit`) bisa diakses publik dari
      internet (bukan di belakang VPN/firewall yang memblokir Xendit) —
      tanpa ini status pembayaran tidak pernah ter-update otomatis.

## Sudah benar di konfigurasi dev, tinggal dibawa apa adanya

- [x] `SESSION_DRIVER=database`, `CACHE_STORE=database` — sudah tepat untuk
      production (bukan `array`/`file` yang tidak cocok multi-worker).
- [x] Rate limiter endpoint sensitif (OTP request/verify, register,
      payment-action) sudah terpasang di `AppServiceProvider::boot()`.
- [x] Test suite terisolasi total dari database dev/production
      (`phpunit.xml` pakai `nexa_testing` terpisah).

## Direkomendasikan, tidak wajib untuk go-live pertama

- [ ] Pasang error tracking (Sentry/Bugsnag/Flare) — saat ini error cuma
      masuk `storage/logs/laravel.log`, tanpa alerting proaktif kalau
      terjadi error di production. Boleh menyusul setelah live berjalan.

## Setelah deploy pertama kali

1. `php artisan migrate --force` (bukan `migrate:fresh` — itu akan
   menghapus seluruh data).
2. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
3. Verifikasi scheduler terdaftar: `php artisan schedule:list`.
4. Verifikasi queue worker jalan: cek proses via `supervisorctl status nexa-queue:*`.
5. Kirim satu notifikasi test lewat `php artisan notify:test --phone=<nomor-staff>`
   untuk konfirmasi WhatsApp gateway production benar-benar mengirim.
