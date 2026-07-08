# CLAUDE.md

File ini memberikan panduan untuk Claude Code (claude.ai/code) saat bekerja dengan kode di repository ini.

> **Bahasa:** Dokumen ini sengaja ditulis dalam Bahasa Indonesia (kecuali istilah teknis, nama file, kode, dan perintah) agar seluruh developer — termasuk yang sedang debugging atau baru mempelajari aplikasi ini — dapat cepat memahami konteks dan arsitektur proyek. Pertahankan Bahasa Indonesia setiap kali file ini diperbarui.

## Status Project

Ini adalah **NEXA**, aplikasi internal untuk XNet (PT. XPlus Network Indonesia) yang akan menjadi tulang punggung administrasi/operasional untuk perusahaan ISP: manajemen pelanggan, katalog produk/layanan, billing, dan pembayaran via Xendit, yang selanjutnya akan berkembang ke integrasi jaringan (MikroTik, OLT) dan operasional ISP secara lebih luas.

Repository saat ini masih berupa skeleton Laravel 13 standar (hasil instalasi `laravel/laravel` baru) — belum ada kode domain, migration, atau modul yang dibangun selain model/migration `User` bawaan. `README.md` adalah dokumen perencanaan arsitektur yang hidup (living document, dalam Bahasa Indonesia) yang menjelaskan arah project; jadikan itu sebagai sumber kebenaran untuk struktur dan konvensi yang dituju, sampai kode yang benar-benar berjalan menetapkan sebaliknya.

## Sebelum Mengambil Keputusan Arsitektur atau Implementasi

- **Baca ulang `README.md` sebelum memulai fitur baru.** Ini adalah living document — pemiliknya meng-update-nya langsung setiap kali arah berubah (alur login, pendekatan integrasi payment, prioritas roadmap, dll), dan bisa berubah antar sesi tanpa ada perubahan kode yang mengikutinya. Jangan mengandalkan ringkasan dari percakapan sebelumnya; selalu cek file yang terkini.
- **Cek `database/database.drawio` sebelum mendesain migration atau skema.** Ini adalah diagram ER draw.io yang menjadi draft/roadmap berjalan untuk desain database (lihat "Draft Desain Database" di bawah untuk ringkasan yang sudah di-parse). File ini terbuka untuk direvisi — jika ada yang terlihat tidak konsisten dengan apa yang sedang diimplementasikan, sampaikan ke user, jangan diam-diam menyimpang atau mengikutinya secara kaku.
- **Update CLAUDE.md ini setiap kali ada keputusan arsitektur/scope/konvensi yang nyata** — baik keputusan itu muncul pertama kali di kode, di `README.md`, maupun di draft drawio. File ini adalah riwayat keputusan yang berjalan, bukan sekadar dokumen onboarding; jaga agar tetap sinkron supaya sesi berikutnya tidak kehilangan arah.

## Perintah yang Umum Digunakan

```bash
# Install dependency PHP dan siapkan .env / app key / sqlite db / migration
composer install
composer run setup      # copy .env, generate key, migrate, npm install, npm build

# Development lokal (menjalankan app + queue listener + pail logs + vite, bersamaan)
composer run dev

# Jalankan seluruh test suite (clear config cache dulu)
composer run test
# setara dengan:
php artisan test

# Jalankan satu file test / filter tertentu
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=nama_method_test

# Frontend
npm run dev             # vite dev server
npm run build            # build production

# Code style (Laravel Pint)
vendor/bin/pint          # perbaiki otomatis
vendor/bin/pint --test   # cek saja, tanpa mengubah

# Tinker REPL
php artisan tinker
```

Database lokal default adalah SQLite (`database/database.sqlite`), dikonfigurasi lewat `.env` (`DB_CONNECTION=sqlite`). Test berjalan di SQLite in-memory (lihat `phpunit.xml`). README menyebutkan MySQL (InnoDB, utf8mb4) sebagai target database produksi — perkirakan ini bisa berubah seiring project berkembang.

## Arah Arsitektur (dari README.md)

Project ini bermaksud mengikuti konvensi berikut saat kode domain mulai ditambahkan — terapkan ini saat membuat fitur baru, jangan default ke controller gemuk:

- **Layering**: Form Request untuk validasi, Service Layer untuk business logic, Eloquent untuk persistence. Jauhkan business logic dari Controller; gunakan dependency injection.
- **Konvensi penamaan** per konsep domain, contoh untuk entitas `Customer`: `CustomerController`, `CustomerService`, `CustomerPolicy`, `CustomerRequest`.
- **API**: aplikasi customer-facing (web/Android/iOS) akan mengakses lewat REST API versioned di `/api/v1`, terpisah dari admin UI berbasis Blade.
- **Pembagian teknologi frontend** — gunakan teknologi sesuai tanggung jawabnya, jangan default pakai Vue:
  - Blade: layout, dashboard, halaman CRUD, form, table.
  - Alpine.js: interaksi ringan (modal, dropdown, accordion, toast, toggle).
  - Vue: hanya untuk komponen yang benar-benar kompleks (dashboard realtime, monitoring jaringan, grafik, wizard multi-step).
- **Integrasi jaringan**: integrasi MikroTik dan OLT (HSGQ) direncanakan di belakang pola adapter/driver supaya vendor baru bisa ditambahkan tanpa menyentuh modul bisnis.
- Soft delete hanya jika benar-benar dibutuhkan, bukan default; timestamp standar Laravel; foreign key dan index yang sesuai diharapkan ada di semua skema.

### Authentication / Login

- Login menggunakan **nomor telepon**, bukan email/password — berbasis OTP, bukan login password tradisional untuk end user.
- OTP dikirim melalui **WhatsApp**, bukan SMS.
- WhatsApp gateway adalah **service terpisah** (`go-whatsapp-web-multidevice`), diakses lewat REST API. NEXA tidak berkomunikasi langsung dengan WhatsApp/Meta.
- NEXA sendiri yang memegang lifecycle OTP — pembuatan, penyimpanan, masa berlaku, dan validasi semuanya terjadi di aplikasi ini, bukan di service WA gateway.

### Payment Gateway (Xendit)

- Menggunakan **Xendit Payment Requests API v3**.
- Integrasi menggunakan **raw HTTP call** — SDK resmi Xendit sengaja tidak digunakan.
- Seluruh pemanggilan Xendit wajib melalui service wrapper internal (tidak dipanggil langsung dari controller), agar integrasi raw-HTTP ini tetap mudah dipelihara dan di-test, serta penanganan webhook (verifikasi signature, idempotency) punya satu tempat yang jelas.

## Draft Desain Database (`database/database.drawio`)

`database/database.drawio` adalah diagram ER draw.io — sebuah **draft roadmap** untuk skema MySQL, bukan spek yang final. Terbuka untuk direvisi; begitu migration sungguhan sudah ada untuk sebuah tabel, migration itulah yang jadi acuan utama, dan diagram dianggap historis/aspirasional untuk yang belum dibangun. Berdasarkan review terakhir, diagram ini mendefinisikan:

- `users` — akun (customer dan/atau staff, dibedakan lewat flag `admin`), dengan phone/KTP/dob/gender, password, `last_login_at`.
- `subdistricts` — data referensi wilayah Indonesia (kecamatan/kota/provinsi, dengan nama yang didenormalisasi).
- `pops` — perangkat PoP jaringan, terhubung ke sebuah subdistrict.
- `coverages` — area cakupan layanan, terhubung ke sebuah PoP.
- `products`, `packages`, `package_product` — katalog; package membundel beberapa product lewat tabel pivot.
- `services` — instance layanan yang dilanggan customer (alamat, coverage, timestamp lifecycle: `activated_at`/`expired_at`/`dismantled_at`/`canceled_at`/`deleted_at`).
- `sales` — order terhadap sebuah service + package (rincian harga: subtotal/discount/tax/admin_fee/grandtotal; lifecycle: `invoiced_at`/`settled_at`/`expired_at`/`canceled_at`).
- `sale_products` — line item untuk sebuah sale.
- `service_activations` / `service_dismantles` — event instalasi/uninstalasi yang terkait sebuah sale.
- `service_tickets` — tiket dukungan terhadap sebuah service.
- `receipts` — kwitansi pembayaran terkait sebuah sale.

Relasi utama: `pops`→`coverages`→`services`; `subdistricts`→`pops`/`services`; `users`→`services`; `products`/`packages`→`package_product`→`sale_products`; `services`→`sales`→{`sale_products`, `service_activations`, `receipts`}; `service_activations`→`service_dismantles`.

Gap yang perlu didiskusikan dengan user sebelum ditulis jadi migration (belum terselesaikan per saat ini): belum ada tabel OTP padahal NEXA yang memegang lifecycle OTP; `receipts` belum punya kolom spesifik Xendit (payment_request_id/method/status/paid_at/raw response) padahal Xendit adalah fokus utama; Role & Permission (item roadmap) belum tercermin selain flag `admin` tunggal di `users`.

## Catatan Stack

- PHP ^8.3, Laravel ^13.8.
- Frontend build via Vite + `laravel-vite-plugin`, Tailwind CSS v4 (lewat `@tailwindcss/vite`), entry point `resources/css/app.css` dan `resources/js/app.js`.
- `bootstrap/app.php` adalah konfigurasi app single-file gaya Laravel 13 (routing, middleware, exception handling) — tidak ada `app/Http/Kernel.php`.
- `app/Providers/AppServiceProvider.php` saat ini adalah satu-satunya provider aplikasi yang terdaftar (lihat `bootstrap/providers.php`).
