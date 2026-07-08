# CLAUDE.md

File ini memberikan panduan untuk Claude Code (claude.ai/code) saat bekerja dengan kode di repository ini.

> **Bahasa:** Dokumen ini sengaja ditulis dalam Bahasa Indonesia (kecuali istilah teknis, nama file, kode, dan perintah) agar seluruh developer — termasuk yang sedang debugging atau baru mempelajari aplikasi ini — dapat cepat memahami konteks dan arsitektur proyek. Pertahankan Bahasa Indonesia setiap kali file ini diperbarui.

## Status Project

Ini adalah **NEXA**, aplikasi internal untuk XNet (PT. XPlus Network Indonesia) yang akan menjadi tulang punggung administrasi/operasional untuk perusahaan ISP: manajemen pelanggan, katalog produk/layanan, billing, dan pembayaran via Xendit, yang selanjutnya akan berkembang ke integrasi jaringan (MikroTik, OLT) dan operasional ISP secara lebih luas.

Repository ini masih tahap awal. Modul domain pertama yang sudah nyata (bukan sekadar shell) adalah **Authentication** (login phone + OTP WhatsApp — lihat "Authentication / Login" di bawah untuk detail implementasi). Modul bisnis lain (Customer, Billing, Xendit, dst.) belum dibangun. `README.md` adalah dokumen perencanaan arsitektur yang hidup (living document, dalam Bahasa Indonesia) yang menjelaskan arah project; jadikan itu sebagai sumber kebenaran untuk struktur dan konvensi yang dituju, sampai kode yang benar-benar berjalan menetapkan sebaliknya.

## Sebelum Mengambil Keputusan Arsitektur atau Implementasi

- **Baca ulang `README.md` sebelum memulai fitur baru.** Ini adalah living document — pemiliknya meng-update-nya langsung setiap kali arah berubah (alur login, pendekatan integrasi payment, prioritas roadmap, dll), dan bisa berubah antar sesi tanpa ada perubahan kode yang mengikutinya. Jangan mengandalkan ringkasan dari percakapan sebelumnya; selalu cek file yang terkini.
- **Cek `database/database_design.drawio` sebelum mendesain migration atau skema.** Ini adalah diagram ER draw.io yang menjadi draft/roadmap berjalan untuk desain database (lihat "Draft Desain Database" di bawah untuk ringkasan yang sudah di-parse). File ini terbuka untuk direvisi — jika ada yang terlihat tidak konsisten dengan apa yang sedang diimplementasikan, sampaikan ke user, jangan diam-diam menyimpang atau mengikutinya secara kaku.
- **Buat dan update `database/database_actual.drawio` sesuai dengan database actual.** Tujuannya agar user bisa mengetahui database, table, dan kolom yang sudah dibuat sampai dengan saat ini.
- **Update CLAUDE.md ini setiap kali ada keputusan arsitektur/scope/konvensi yang nyata** — baik keputusan itu muncul pertama kali di kode, di `README.md`, maupun di draft drawio. File ini adalah riwayat keputusan yang berjalan, bukan sekadar dokumen onboarding; jaga agar tetap sinkron supaya sesi berikutnya tidak kehilangan arah.

## Untuk Commit Git dan Push ke Github

- Selalu gunakan **bahasa Indonesia** untuk mengisi commit di GIT

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

Database lokal (dev) adalah **MySQL** (`nexa`), dikonfigurasi lewat `.env` (`DB_CONNECTION=mysql`, host `127.0.0.1:3306`) — bukan SQLite lagi. Test suite juga berjalan di MySQL, pakai database terpisah **`nexa_testing`** (lihat `phpunit.xml`, `DB_CONNECTION=mysql`/`DB_DATABASE=nexa_testing`) supaya `RefreshDatabase` tidak pernah menyentuh data dev di `nexa`. `pdo_sqlite` sengaja tidak dipasang di environment dev ini — kalau butuh sqlite di environment lain, sesuaikan lagi `phpunit.xml`. README menyebutkan MySQL (InnoDB, utf8mb4) sebagai target database produksi, jadi keputusan ini juga menyelaraskan dev/test dengan target produksi lebih awal.

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

Sudah **diimplementasikan sungguhan** (bukan lagi shell/stub) — login pakai nomor telepon + OTP WhatsApp, lengkap dengan migration, service layer, dan middleware auth.

- Login menggunakan **nomor telepon**, bukan email/password — berbasis OTP, bukan login password tradisional untuk end user.
- OTP dikirim melalui **WhatsApp**, bukan SMS.
- WhatsApp gateway adalah **service terpisah** (`go-whatsapp-web-multidevice`), diakses lewat REST API. NEXA tidak berkomunikasi langsung dengan WhatsApp/Meta.
- NEXA sendiri yang memegang lifecycle OTP — pembuatan, penyimpanan, masa berlaku, dan validasi semuanya terjadi di aplikasi ini, bukan di service WA gateway.
- **Tidak ada auto-register.** Nomor yang belum ada di tabel `users` ditolak saat minta OTP (`SendOtpRequest`, rule `exists:users,phone`) — pembuatan akun customer adalah tanggung jawab modul Customer yang belum dibangun. Untuk dev lokal, `database/seeders/AdminUserSeeder.php` menyediakan satu akun admin (`phone` `6281234567890`) via `php artisan migrate:fresh --seed`.
- **Format nomor telepon: selalu disimpan dalam format internasional (kode negara `62` + nomor, tanpa `0` di depan), kolom `phone` bertipe `unsignedBigInteger` (BIGINT UNSIGNED) — bukan string/VARCHAR.** Keputusan sadar: nomor Indonesia dianggap selalu diawali kode negara 62 begitu dinormalisasi, jadi tidak ada leading-zero bermakna yang perlu dijaga (leading `0` yang ada di input user hanyalah trunk prefix domestik yang dibuang, bukan bagian dari nomor kanonis). Field login di `resources/views/auth/login.blade.php` tetap **satu input polos** (placeholder `81234567890`, tanpa badge/prefix visual `+62`) — user cukup ketik nomor lokal tanpa `0`/`62`, backend yang menormalisasi. Normalisasi dilakukan oleh `App\Support\PhoneNumber::normalize()` (dipanggil dari `SendOtpRequest::prepareForValidation()`): input dibersihkan dari karakter non-digit, lalu kalau diawali `0` diganti jadi `62`, kalau sudah diawali `62` dibiarkan, selain itu `62` ditambahkan di depan. Pakai helper ini lagi (jangan re-implement) di modul lain yang menerima input nomor telepon (mis. nanti Customer). `User::phone` di-cast `'integer'` di model.
- **Skema**: migration `..._add_phone_admin_last_login_at_to_users_table` menambah `phone` (unsignedBigInteger, nullable, unique — nullable supaya tidak konflik dengan baris user yang belum punya phone; "harus terdaftar" ditegakkan di Form Request, bukan di DB), `admin` (boolean), `last_login_at` ke `users`. Kolom domain customer dari draft drawio (`ktp`/`dob`/`gender`/`code`) **sengaja ditunda** ke migration terpisah saat modul Customer dikerjakan. Tabel baru `otp_codes` (`app/Models/OtpCode.php`) — `user_id` (FK, bukan kolom `phone` terpisah, karena OTP hanya bisa diminta untuk user yang sudah ada), `code_hash` (HMAC-SHA256, bukan bcrypt — OTP berumur pendek/sekali pakai dan sudah dilindungi rate limit + attempt lockout), `expires_at`, `consumed_at`, `attempts`.
- **Service layer**: `app/Services/OtpService.php` (generate/simpan/expire/validasi kode, cooldown resend 60 detik via `Cache`, lockout setelah `OTP_MAX_ATTEMPTS` percobaan salah), dipanggil dari `LoginController` (sudah bukan stub lagi, di-inject `OtpService`).
- **WhatsApp gateway pakai pola driver/adapter** (`app/Services/Whatsapp/`): interface `WhatsappGateway`, driver default **`log`** (`LogWhatsappGateway` — tulis OTP ke `storage/logs/laravel.log`, dipakai karena server `go-whatsapp-web-multidevice` sungguhan belum tersedia di dev), dan skeleton `HttpWhatsappGateway` (raw `Http::` facade sesuai mandat README, endpoint masih placeholder — belum dikonfirmasi ke server nyata). Pilihan driver dari `config('services.whatsapp.driver')` / env `WHATSAPP_GATEWAY_DRIVER`, di-bind di `app/Providers/WhatsappServiceProvider.php` (terdaftar di `bootstrap/providers.php`).
- **Pengiriman OTP lewat job `app/Jobs/SendOtpWhatsappNotification.php`, tapi di-dispatch di koneksi queue `sync`** (`config('otp.queue_connection')` / env `OTP_QUEUE_CONNECTION`, default `sync`), **bukan ikut `QUEUE_CONNECTION=database` global**. Ini keputusan sadar, bukan default Laravel: percobaan pertama pakai `database` sempat menyebabkan job nyangkut di tabel `jobs` tanpa pernah diproses karena tidak ada `queue:work`/`queue:listen` yang jalan di dev — OTP jadi tidak pernah benar-benar "terkirim" (tidak ke-log), padahal baris OTP di DB sudah dibuat, bikin bingung ("kode di log kok tidak cocok" — padahal log-nya memang belum pernah ditulis, itu log basi dari percobaan sebelumnya). `sync` membuat job jalan inline saat request tanpa butuh worker terpisah. Kalau nanti `HttpWhatsappGateway` sungguhan dipakai di production dan panggilan HTTP-nya berpotensi lambat/nge-block request, pertimbangkan ubah `OTP_QUEUE_CONNECTION` kembali ke `database` (atau koneksi queue khusus) — jangan asumsikan `sync` cocok selamanya.
- **Routing & middleware**: `/login` + `/login/otp` dalam grup `guest`; `/dashboard` + `POST /logout` (baru) dalam grup `auth`. `bootstrap/app.php` pakai `redirectGuestsTo('/login')`/`redirectUsersTo('/dashboard')` (built-in Laravel 13). Rate limiter `otp-request` (3/menit per IP+phone) dan `otp-verify` (10/menit per IP) didaftarkan di `AppServiceProvider::boot()` — karena tidak ada `RouteServiceProvider` terpisah di skeleton Laravel 13 ini.
- Tombol "Keluar" di `header.blade.php` sudah di-wire ke `POST /logout` (form + `@csrf`), menggantikan placeholder `<a href="#">`.
- Test: `tests/Feature/Auth/LoginFlowTest.php` — pakai fake `WhatsappGateway` (bind instance yang menangkap kode asli) untuk membaca kode OTP tanpa bergantung pada skema hash internal.

### Payment Gateway (Xendit)

- Menggunakan **Xendit Payment Requests API v3**.
- Integrasi menggunakan **raw HTTP call** — SDK resmi Xendit sengaja tidak digunakan.
- Seluruh pemanggilan Xendit wajib melalui service wrapper internal (tidak dipanggil langsung dari controller), agar integrasi raw-HTTP ini tetap mudah dipelihara dan di-test, serta penanganan webhook (verifikasi signature, idempotency) punya satu tempat yang jelas.

## Draft Desain Database (`database/database_design.drawio`)

`database/database_design.drawio` adalah diagram ER draw.io — sebuah **draft roadmap** untuk skema MySQL, bukan spek yang final. Terbuka untuk direvisi; begitu migration sungguhan sudah ada untuk sebuah tabel, migration itulah yang jadi acuan utama, dan diagram dianggap historis/aspirasional untuk yang belum dibangun. Berdasarkan review terakhir, diagram ini mendefinisikan:

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

Gap yang sudah diselesaikan: tabel OTP (`otp_codes`) sudah dibangun dari nol (tidak ada di diagram sama sekali) — lihat "Authentication / Login" di atas. `users.phone`/`admin`/`last_login_at` juga sudah dimigrasikan (minimal, tanpa `ktp`/`dob`/`gender`/`code` yang sengaja ditunda ke modul Customer). `subdistricts` juga sudah dibangun sesuai kolom di diagram (migration `2026_07_08_100002_create_subdistricts_table.php`, model `App\Models\Subdistrict`, seeder `database/seeders/SubdistrictSeeder.php` yang meng-eksekusi dump SQL siap-pakai `database/seeders/subdistricts.sql` — ~84 ribu baris data wilayah Kemendagri, diimpor lewat `DB::unprepared` sekali saat tabel masih kosong). Tabel ini murni data referensi read-only (lookup wilayah provinsi/kota/kecamatan/kelurahan-desa yang didenormalisasi, kolom `district_id`/`city_id`/`province_id` adalah kode wilayah pemerintah, bukan FK — tidak ada tabel `districts`/`cities`/`provinces` terpisah) — belum ada controller/route/UI untuk ini, dan belum ada FK sungguhan _ke_ tabel ini karena `pops`/`services` (yang menurut diagram terhubung ke `subdistricts`) belum dibangun.

Ada file drawio kedua, `database/database_actual.drawio`, dengan tujuan berbeda dari diagram desain di atas: itu bukan draft, melainkan cermin skema yang **sungguhan** sudah dimigrasikan ke database saat ini (`users`, `otp_codes`, `subdistricts`), kolomnya diambil langsung dari migration — bukan dari diagram desain, karena keduanya bisa saja menyimpang (mis. diagram desain di atas masih menyebut kolom `code` di `users` yang sebenarnya tidak pernah dimigrasikan). Wajib di-update tiap kali ada migration baru landing, sama seperti kewajiban update CLAUDE.md ini sendiri.

Gap yang masih perlu didiskusikan dengan user sebelum ditulis jadi migration: `receipts` belum punya kolom spesifik Xendit (payment_request_id/method/status/paid_at/raw response) padahal Xendit adalah fokus utama; Role & Permission (item roadmap) belum tercermin selain flag `admin` tunggal di `users`.

## Referensi Desain UI

Tampilan NEXA dibuat meniru template admin berbayar **Metronic v8.1.8 (varian "demo1")**, terletak di mesin dev pada `/var/www/html/templates/metronic_v8.1.8/html/demo1/dist/` (di luar repo — bukan aset yang di-commit). Metronic aslinya berbasis Bootstrap 5, tapi di NEXA **direproduksi ulang dengan Tailwind CSS + Alpine.js** (bukan Bootstrap asli) supaya konsisten dengan stack yang sudah diputuskan — lihat memory/percakapan sebelumnya untuk detail keputusan ini. Dua project ISP sebelumnya, `/home/anggara/Webapp/xnet/app11` dan `/home/anggara/Webapp/xnet/app12` (di luar repo ini), juga jadi rujukan branding (logo) dan pola alur login phone+OTP.

- **Palet warna** (`resources/css/app.css`, blok `@theme`) diambil dari warna asli demo1 (`src/sass/components/_variables.custom.scss` di template, bukan warna default Metronic versi lama): `primary #009ef7`, `success #50cd89`, `info #7239ea`, `danger #f1416c`, `warning #ffc700` (masing-masing punya varian `-active` dan `-light`). Skala abu-abu (`--color-gray-100..900`) juga ditimpa dengan grayscale asli Metronic (`#F9F9F9` → `#181C32`), menggantikan skala gray default Tailwind.
- **Font**: Inter (via `bunny()` di `vite.config.js`), menggantikan `Instrument Sans` bawaan starter Laravel.
- **Alpine.js** ditambahkan sebagai dependency npm (`resources/js/app.js` meng-import & start Alpine) — dipakai untuk semua interaktivitas ringan (toggle sidebar mobile, dropdown profil), bukan JS Bootstrap milik Metronic.
- **Konvensi komponen layout** (`resources/views/components/`):
    - `auth-layout.blade.php` — kartu terpusat polos untuk halaman auth (meniru varian "corporate" dari 4 gaya auth Metronic — dipilih karena tanpa background image dekoratif, paling mudah direplikasi).
    - `app-layout.blade.php` — shell utama setelah login: menggabungkan `sidebar.blade.php` (sidebar gelap `bg-gray-900`, accordion menu via Alpine), `header.blade.php` (topbar, search, notifikasi, dropdown profil), `footer.blade.php`. State buka/tutup sidebar mobile (`sidebarOpen`) di-`x-data` di `app-layout`, dipakai bareng oleh `sidebar` dan `header` karena keduanya di-render dalam scope DOM yang sama.
    - Item menu di `sidebar.blade.php` mengikuti daftar modul roadmap README (Dashboard, Pelanggan, Produk & Paket, Layanan, Billing, Tiket, Inventaris, Vendor & Supplier, Laporan, Pengaturan) — **hanya "Dashboard" yang mengarah ke route sungguhan**, sisanya masih `href="#"` placeholder sampai modulnya dibangun.
- **Halaman auth** (`resources/views/auth/login.blade.php`, `verify-otp.blade.php`) dan **dashboard placeholder** (`resources/views/dashboard.blade.php`) sudah dibuat, dan **`app/Http/Controllers/Auth/LoginController.php` sudah bukan stub lagi** — lihat "Authentication / Login" di atas untuk detail implementasi nyata (OTP, WhatsApp gateway, middleware auth). Dashboard sendiri (`dashboard.blade.php`) masih placeholder statis, belum ada data nyata.
- Kartu statistik dashboard & badge warna ditulis dengan **kelas Tailwind literal** (bukan interpolasi `bg-{{ $var }}-light`) supaya tetap terdeteksi oleh content-scanner Tailwind v4 saat build — pertahankan pola ini di halaman lain yang punya warna dinamis per-item.
- Belum ada halaman modul bisnis lain (Customer/Billing/dll) yang dibangun — replikasi Metronic baru sebatas shell navigasi (login, OTP, layout dashboard kosong), sesuai cakupan yang disepakati.

### Dark mode

- Dark mode ikut preferensi sistem (`prefers-color-scheme`) secara default, tapi user bisa override manual lewat tombol toggle — permintaan eksplisit user supaya tidak dipaksa satu mode saja.
- Tailwind v4 dikonfigurasi pakai varian `dark:` berbasis **class**, bukan cuma media query: `@custom-variant dark (&:where(.dark, .dark *));` di `resources/css/app.css`. Ini wajib ada supaya toggle manual (menambah/menghapus class `dark` di `<html>`) benar-benar mengubah tampilan.
- `resources/views/components/theme-init.blade.php` — script kecil yang **harus** dirender paling awal di `<head>` (sebelum elemen lain), tugasnya membaca `localStorage.theme` (kalau user pernah memilih manual) atau fallback ke `matchMedia('(prefers-color-scheme: dark)')`, lalu toggle class `dark` di `<html>` sebelum browser sempat menggambar (mencegah flash tema salah). Juga listen ke perubahan preferensi sistem selama user belum override manual.
- `resources/views/components/theme-toggle.blade.php` — tombol Alpine yang toggle class `dark` + simpan pilihan ke `localStorage.theme` ('light'/'dark'). Dipasang di `header.blade.php` (untuk halaman ber-sidebar) dan pojok kanan-atas `auth-layout.blade.php` (halaman login/OTP, karena belum ada header di sana).
- Setiap komponen/halaman baru yang punya warna latar/teks eksplisit **wajib** ditambahi pasangan `dark:` yang sepadan (pola: `bg-white` → `+ dark:bg-gray-800`, `text-gray-900` → `+ dark:text-white`, `border-gray-300` → `+ dark:border-gray-700`, dst.) — jangan biarkan halaman baru cuma punya tampilan light mode. Sidebar (`sidebar.blade.php`) terkecuali: dia memang didesain gelap permanen ("dark-sidebar" ala Metronic) terlepas dari mode terang/gelap keseluruhan app.

### Logo mengikuti tema (light/dark)

`public/images/logo/` berisi dua varian logo (aset sementara dari `~/Webapp/xnet/app11`), keduanya PNG transparan berisi mark "X" saja (bukan logo dengan warna latar solid, meski namanya menyiratkan begitu):

- `logo-white-bg.png` — X berwarna **hitam**, didesain untuk dipasang di atas latar **terang**.
- `logo-black-bg.png` — X berwarna **putih**, didesain untuk dipasang di atas latar **gelap**.

Aturan pemakaian (dicontek dari pola yang sama di `~/Webapp/xnet/app/`, yang pakai trik CSS `dark:invert` pada satu logo hitam — di NEXA dipakai pendekatan dua file dengan toggle visibility karena kita sudah punya kedua varian siap pakai, bukan filter invert):

- **Area yang latarnya ikut berubah terang/gelap** (mis. `auth-layout.blade.php`, tempat logo duduk di atas `bg-gray-100 dark:bg-gray-900`) — render **kedua** `<img>`, satu dengan class `dark:hidden` (pakai `logo-white-bg.png`, utk light mode) dan satu lagi `hidden dark:block` (pakai `logo-black-bg.png`, utk dark mode).
- **Area yang latarnya selalu gelap terlepas dari tema app** (`sidebar.blade.php` dan header/nav gelap di `welcome.blade.php`, keduanya `bg-gray-900` permanen) — **selalu** pakai `logo-black-bg.png` (X putih) saja, tanpa toggle, karena latar belakangnya tidak pernah berubah jadi terang.

Kalau menambah logo di tempat baru, cek dulu apakah latar elemen tersebut ikut berubah dengan tema app atau tetap satu warna — itu yang menentukan perlu toggle dua gambar atau cukup satu varian tetap.

## Catatan Stack

- PHP ^8.3, Laravel ^13.8.
- Frontend build via Vite + `laravel-vite-plugin`, Tailwind CSS v4 (lewat `@tailwindcss/vite`), Alpine.js (npm, di-start di `resources/js/app.js`), entry point `resources/css/app.css` dan `resources/js/app.js`.
- `bootstrap/app.php` adalah konfigurasi app single-file gaya Laravel 13 (routing, middleware, exception handling) — tidak ada `app/Http/Kernel.php`.
- `app/Providers/AppServiceProvider.php` saat ini adalah satu-satunya provider aplikasi yang terdaftar (lihat `bootstrap/providers.php`).
