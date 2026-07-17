# API Customer-Facing NEXA (`/api/v1`)

> **Status:** v1, sudah diimplementasikan penuh dan diuji (lihat
> `CLAUDE.md` section "API Customer-Facing (`/api/v1`)" untuk keputusan
> arsitektur & alasan di baliknya). Dokumen ini adalah panduan pemakaian
> praktis — cara memanggil tiap endpoint, contoh request/response, dan
> cara mencobanya lewat Postman — bukan dokumen desain.

Dokumen ini ditujukan untuk siapa pun yang akan membangun **aplikasi
pelanggan terpisah** (Android/iOS/Web) yang terhubung ke NEXA lewat REST
API ini, dan untuk uji coba manual lewat Postman/curl selama development.

## 1. Ringkasan

- API ini **khusus untuk pelanggan** (role `customer`) — bukan untuk
  staff/admin NEXA (mereka pakai login admin berbasis session di
  `/login`, terpisah total dari API ini).
- Autentikasi pakai **Laravel Sanctum, personal access token (Bearer
  token)** — bukan session/cookie.
- Login tetap lewat **OTP WhatsApp**, sama seperti admin, tapi alurnya
  API-friendly (dua langkah: minta kode → verifikasi kode), bukan
  berbasis session seperti form login admin.
- Semua response berbentuk **JSON**.

## 2. Base URL

Dev lokal (sesuai `APP_URL` di `.env` project ini):

```
http://nexa.xplus.test:8080/api/v1
```

Kalau menjalankan lewat `php artisan serve` tanpa domain custom, base URL
mengikuti port yang ditampilkan (default `http://127.0.0.1:8000/api/v1`).

URL produksi belum ada — menyusul saat NEXA sungguhan di-deploy.

## 3. Header Wajib

| Header | Kapan | Kenapa |
|---|---|---|
| `Accept: application/json` | **Semua request, tanpa kecuali** | Tanpa ini, error (validasi, 404, dll) bisa dikembalikan sebagai halaman HTML, bukan JSON. |
| `Content-Type: application/json` | Request yang mengirim body JSON (`POST`) | Supaya Laravel mem-parsing body sebagai JSON, bukan form-urlencoded. |
| `Authorization: Bearer <token>` | Semua endpoint KECUALI `POST /auth/otp/request` dan `POST /auth/otp/verify` | Token didapat dari response `POST /auth/otp/verify` (lihat bagian Autentikasi). |

## 4. Autentikasi (Alur OTP → Token)

Bagian ini untuk pelanggan yang **akunnya sudah ada** di NEXA (dibuat
lewat admin, ATAU sudah pernah daftar sendiri lewat `/auth/register` —
lihat bagian 5). Kalau pelanggan **belum punya akun sama sekali**,
langsung ke bagian 5, bukan ke sini — endpoint di bagian ini menolak
nomor yang belum terdaftar (`Rule::exists`, kebalikan dari `/auth/register`
yang justru mensyaratkan nomor BELUM terdaftar).

Kalau nomor terdaftar tapi rolenya BUKAN `customer` (staff/admin), request
juga ditolak — API ini kebalikan dari login admin NEXA, yang menolak
role `customer`.

Alurnya 3 langkah:

```
1. POST /auth/otp/request     { phone }
   → 202 { message, verification_token }
   → kode OTP 6 digit dikirim ke WhatsApp pelanggan

2. POST /auth/otp/verify      { verification_token, code }
   → 200 { token, user }
   → "token" inilah Bearer token untuk semua request selanjutnya

3. Semua request berikutnya pakai header:
   Authorization: Bearer <token>
```

`verification_token` cuma berlaku sekali pakai dan kedaluwarsa mengikuti
`config('otp.ttl_minutes')` (default **5 menit**) — sama dengan masa
berlaku kode OTP-nya. Token Sanctum yang didapat dari langkah 2 **tidak
punya masa kedaluwarsa** di v1 (tetap valid sampai `POST /auth/logout`
dipanggil).

### 4.1 `POST /auth/otp/request`

Minta kode OTP dikirim ke WhatsApp pelanggan.

**Body:**

```json
{ "phone": "81234567890" }
```

Nomor boleh ditulis apa adanya (dengan/tanpa awalan `0`/`62`) — backend
menormalisasi otomatis (`App\Support\PhoneNumber::normalize()`).

**Sukses — `202 Accepted`:**

```json
{
  "message": "Kode OTP telah dikirim lewat WhatsApp.",
  "verification_token": "rr8r7RyszgRakFkbSi2Qh1AXuBXps8vDHYaQYK30dprFcR2HRypx3LTnpRNWikxF"
}
```

**Error — `422 Unprocessable Content`** (contoh, `errors.phone` bisa berisi
salah satu dari):

| Pesan | Kapan |
|---|---|
| `"Nomor telepon tidak terdaftar."` | Nomor belum ada di NEXA sama sekali. |
| `"Nomor ini bukan nomor pelanggan."` | Nomor terdaftar tapi rolenya staff/admin, bukan `customer`. |
| `"Format nomor telepon tidak valid."` | Nomor tidak sesuai format (9–15 digit). |

**Error — `429 Too Many Requests`** (dua kemungkinan, response sama):

```json
{ "message": "Mohon tunggu sebentar sebelum meminta kode baru." }
```

Terjadi kalau: (a) diminta ulang sebelum cooldown resend selesai
(`config('otp.resend_cooldown_seconds')`, default **60 detik**), atau
(b) sudah melewati rate limit `api-otp-request` (**3 kali/menit** per
kombinasi IP+nomor telepon).

### 4.2 `POST /auth/otp/verify`

**Body:**

```json
{
  "verification_token": "rr8r7RyszgRakFkbSi2Qh1AXuBXps8vDHYaQYK30dprFcR2HRypx3LTnpRNWikxF",
  "code": "186824"
}
```

**Sukses — `200 OK`:**

```json
{
  "token": "2|2DKjjehgRU7vLNbbyS1sLGoRHl7cXx3T9SkeuPWLc8ffa0a1",
  "user": {
    "code": "HTRW5O",
    "name": "Customer API Uji Coba",
    "phone": "6289988877766",
    "email": "customer@example.com",
    "nik": null,
    "gender": null,
    "birth_date": null
  }
}
```

> Perhatikan: response ini **TIDAK** dibungkus `{"data": ...}` — beda
> dari endpoint lain (lihat bagian "Bentuk Response" di bawah).

**Error — `422 Unprocessable Content`:**

```json
{ "message": "Sesi verifikasi tidak valid atau sudah kedaluwarsa." }
```

Muncul kalau `verification_token` salah/sudah kedaluwarsa/sudah dipakai
(sekali pakai, tidak bisa direuse), atau role user berubah setelah
langkah 1.

```json
{ "message": "Kode OTP salah atau sudah kedaluwarsa." }
```

Muncul kalau `code` salah (tiap kesalahan menambah hitungan percobaan —
setelah `config('otp.max_attempts')` kali salah, default **5**, kode itu
terkunci dan pelanggan harus minta kode baru) atau kode sudah kedaluwarsa.

**Error — `429 Too Many Requests`** — rate limit `api-otp-verify`
(**10 kali/menit** per IP).

### 4.3 `POST /auth/logout`

*Butuh token.* Menghapus token yang sedang dipakai (token lain milik
pelanggan yang sama, kalau ada, tidak ikut terhapus).

**Sukses — `200 OK`:**

```json
{ "message": "Berhasil keluar." }
```

Setelah ini, token yang sama akan ditolak `401` di request berikutnya.

## 5. Registrasi Pelanggan Baru

Untuk pelanggan yang **belum punya akun sama sekali** di NEXA — beda dari
alur di bagian 4 (login untuk akun yang sudah ada). Dua langkah, mirip
pola login (minta OTP → verifikasi), tapi **nomor yang diminta di sini
justru harus BELUM terdaftar**, dan verifikasi kode langsung membuat akun
sekaligus mengembalikan Bearer token (tidak perlu memanggil
`/auth/otp/verify` lagi setelahnya):

```
1. POST /auth/register/request-otp   { phone }
   → 202 { message, registration_token }
   → kode OTP 6 digit dikirim ke WhatsApp nomor tsb

2. POST /auth/register                { registration_token, code, name, email }
   → 201 { token, user }
   → akun `customer` baru langsung dibuat, "token" siap dipakai
```

Data yang diminta saat daftar sengaja **minimal** (nama, telepon, email
saja) — NIK dan foto KTP **tidak** diminta di sini, akun bisa dilengkapi
datanya belakangan oleh staff. Nomor telepon **tidak diminta ulang** di
langkah 2 — selalu diambil dari nomor yang sudah terbukti diverifikasi di
langkah 1 (tidak bisa disubstitusi nomor lain).

### 5.1 `POST /auth/register/request-otp`

**Body:**

```json
{ "phone": "85512340001" }
```

**Sukses — `202 Accepted`:**

```json
{
  "message": "Kode OTP telah dikirim lewat WhatsApp.",
  "registration_token": "8KtzUjoCtkZhFuUwD3oBJTmISmXR8j7Rheu0iVJfUHta605kuU9Vdec82PdqQ0hX"
}
```

**Error — `422 Unprocessable Content`:**

```json
{ "message": "Nomor telepon ini sudah terdaftar. Silakan masuk lewat menu login.", "errors": {"phone": ["Nomor telepon ini sudah terdaftar. Silakan masuk lewat menu login."]} }
```

Muncul kalau nomor itu **sudah** terdaftar (baik sebagai `customer`
maupun staff) — pengguna seharusnya pakai alur login (bagian 4), bukan
daftar lagi.

**Error — `429 Too Many Requests`** — sama seperti bagian 4.1: cooldown
resend (`config('registration_otp.resend_cooldown_seconds')`, default
**60 detik**) atau rate limit `api-register-otp-request` (**3 kali/menit**
per kombinasi IP+nomor).

### 5.2 `POST /auth/register`

**Body:**

```json
{
  "registration_token": "8KtzUjoCtkZhFuUwD3oBJTmISmXR8j7Rheu0iVJfUHta605kuU9Vdec82PdqQ0hX",
  "code": "910508",
  "name": "budi santoso",
  "email": "budi.santoso@example.com"
}
```

`name` dinormalisasi otomatis ke Title Case (`"budi santoso"` →
`"Budi Santoso"`), tidak perlu dikapitalisasi manual dari aplikasi.

**Sukses — `201 Created`:**

```json
{
  "token": "4|MntsGFq7j7L4Swfx3bZWvILLtky1WQRZcaWwIGExa7750c63",
  "user": {
    "code": "UOCSBI",
    "name": "Budi Santoso",
    "phone": "6285512340001",
    "email": "budi.santoso@example.com",
    "nik": null,
    "gender": null,
    "birth_date": null
  }
}
```

> Bentuk response ini **identik** dengan `POST /auth/otp/verify` (token +
> user, tanpa pembungkus `data`) — aplikasi pelanggan bisa memakai kode
> penanganan yang sama untuk hasil daftar maupun hasil login.

**Error — `422 Unprocessable Content`** — kemungkinan pesan:

| Pesan | Kapan |
|---|---|
| `"Kode OTP salah, sudah kedaluwarsa, atau token verifikasi tidak valid."` | `registration_token`/`code` salah, kedaluwarsa, atau sudah pernah dipakai (sekali pakai). |
| `"Nomor telepon ini sudah terdaftar. Silakan masuk lewat menu login."` | Kasus langka: nomor sempat didaftarkan lewat jalur lain (mis. staff) tepat di antara langkah 1 dan 2. |
| `errors.email: ["Email ini sudah terdaftar untuk pengguna lain."]` | Email sudah dipakai akun lain — **kode OTP TIDAK ikut terbakar** di kasus ini (validasi email dicek sebelum kode diverifikasi), jadi bisa langsung dicoba ulang dengan email lain tanpa perlu minta kode baru. |
| `errors.name`/`errors.email` lain | Field wajib kosong / format salah. |

**Error — `429 Too Many Requests`** — rate limit `api-register` (**10
kali/menit** per IP).

## 6. Bentuk Response

Dua pola berbeda dipakai secara sengaja:

- **Endpoint auth** (`request`/`verify`/`logout`) — array polos, field
  langsung di root (`{"token": "...", "user": {...}}`), **tidak** ada
  pembungkus `data`.
- **Semua endpoint lain** (Profil, Layanan, Invoice, Tiket) — dibungkus
  `{"data": ...}`, baik untuk satu item (`{"data": {...}}`) maupun daftar
  (`{"data": [...]}`). Ini perilaku default Laravel API Resource, bukan
  keputusan kustom — jangan berasumsi field ada di root untuk endpoint
  ini.

## 7. Format Error

| Status | Kapan | Bentuk body |
|---|---|---|
| `401 Unauthorized` | Token tidak ada / salah / sudah di-logout | `{"message": "Unauthenticated."}` |
| `404 Not Found` | Resource tidak ada, **atau ada tapi bukan milik pelanggan yang login** | `{"message": "No query results for model [...]."}` |
| `422 Unprocessable Content` | Validasi gagal | `{"message": "...", "errors": {"field": ["pesan..."]}}` |
| `429 Too Many Requests` | Rate limit / cooldown OTP | `{"message": "..."}` |

**Penting soal 404 vs 403**: mengakses layanan/tagihan/tiket milik
pelanggan lain **selalu mengembalikan `404`, bukan `403`** — desain
sengaja (lihat CLAUDE.md), supaya tidak ada perbedaan respons yang bisa
dipakai menebak-nebak data (`kode`) milik orang lain. Anggap `404` di
endpoint manapun sebagai "tidak ditemukan ATAU bukan milik Anda".

## 8. Referensi Endpoint

Semua endpoint di bawah ini (kecuali keempat endpoint auth/registrasi di
bagian 4 & 5) butuh header `Authorization: Bearer <token>`.

### `GET /me`

Profil pelanggan yang sedang login.

```bash
curl -H "Accept: application/json" \
     -H "Authorization: Bearer $TOKEN" \
     http://nexa.xplus.test:8080/api/v1/me
```

```json
{
  "data": {
    "code": "HTRW5O",
    "name": "Customer API Uji Coba",
    "phone": "6289988877766",
    "email": "customer@example.com",
    "nik": null,
    "gender": null,
    "birth_date": null
  }
}
```

`nik`/`gender`/`birth_date` bernilai `null` kalau data KYC pelanggan
belum dilengkapi staff lewat admin NEXA.

### `GET /services`

Daftar seluruh layanan (Service) milik pelanggan yang login. **Tidak ada
pagination** (`->get()` polos) — wajar untuk jumlah layanan per pelanggan
yang realistis kecil.

```json
{
  "data": [
    {
      "code": "SRV588124",
      "status": "pending_payment",
      "status_label": "Menunggu Pembayaran",
      "address": "Gg. Asia Afrika No. 955, Tidore Kepulauan 94863, Jateng",
      "residential_name": "PT Agustina Residence",
      "rw": "77",
      "rt": "98",
      "coverage": { "code": "COV639449", "name": "Cakupan Sentot Alibasa" },
      "package": { "code": "PKG826772", "name": "Internet Basic 10 Mbps" },
      "activated_at": null,
      "expired_at": null,
      "suspended_at": null,
      "dismantled_at": null
    }
  ]
}
```

`status` salah satu dari: `pending_payment`, `pending_installation`,
`installing`, `active`, `suspended`, `canceled`, `pending_dismantle`,
`dismantling`, `dismantled` — `status_label` adalah versi Bahasa
Indonesia siap tampil.

### `GET /services/{code}`

Detail satu layanan (bentuk sama seperti satu item di atas, tapi tanpa
pembungkus array — `{"data": {...}}`). `{code}` adalah kode layanan (mis.
`SRV588124`), bukan id database.

Layanan yang tidak ada atau bukan milik pelanggan login → `404`.

### `GET /services/{code}/invoices`

Daftar tagihan (Sale) untuk layanan tsb, terbaru dulu.

```json
{
  "data": [
    {
      "code": "SAL687385",
      "status": "invoiced",
      "status_label": "Menunggu Pembayaran",
      "is_starter": false,
      "is_renewal": false,
      "total": 0,
      "discount": 0,
      "subtotal": 0,
      "tax": 0,
      "admin_fee": 0,
      "grandtotal": 250000,
      "invoiced_at": "2026-07-17T13:55:31+07:00",
      "expired_at": null,
      "settled_at": null,
      "canceled_at": null,
      "checkout_url": null
    }
  ]
}
```

`status` adalah status turunan (bukan kolom eksplisit di database), salah
satu dari:

| `status` | `status_label` | Artinya |
|---|---|---|
| `draft` | Draft | Belum ditagih. |
| `invoiced` | Menunggu Pembayaran | Sudah ditagih, belum lunas/batal. |
| `settled` | Lunas | Sudah dibayar. |
| `canceled` | Dibatalkan | Batal (menang atas `settled` kalau keduanya somehow terisi). |

`checkout_url` — link halaman pembayaran NEXA (`/pay/{receipt}`, publik,
tanpa perlu login, ada verifikasi OTP-nya sendiri di sana). **Selalu
`null`** kalau tagihan belum pernah diproses ke Xendit atau kalau
tagihannya gratis (Rp 0, auto-lunas tanpa proses pembayaran).

### `GET /services/{code}/invoices/{saleCode}`

Detail satu tagihan (bentuk sama seperti satu item di atas). Tagihan yang
tidak ada, atau ada tapi layanannya bukan milik pelanggan login → `404`.

### `GET /services/{code}/tickets`

Daftar tiket (keluhan/permintaan) untuk layanan tsb, terbaru dulu.

```json
{
  "data": [
    {
      "code": "TIK000001",
      "category": "billing",
      "category_label": "Billing",
      "subject": "Uji Coba Tiket",
      "description": "Deskripsi uji coba end-to-end.",
      "status": "open",
      "status_label": "Terbuka",
      "resolution_notes": null,
      "claimed_at": null,
      "solved_at": null,
      "created_at": "2026-07-17T13:56:20+07:00"
    }
  ]
}
```

`category` salah satu dari `teknis`, `billing`, `permintaan`, `lainnya`.
`status` salah satu dari `open` (Terbuka), `in_progress` (Sedang
Ditangani), `resolved` (Selesai).

### `POST /services/{code}/tickets`

Buat tiket baru untuk layanan tsb.

**Body:**

```json
{
  "category": "billing",
  "subject": "Tagihan tidak sesuai",
  "description": "Jumlah tagihan bulan ini berbeda dari biasanya."
}
```

`category` **wajib** salah satu dari `teknis`/`billing`/`permintaan`/
`lainnya`. `subject` maksimal 255 karakter. Tidak ada field `service_id`
di body — layanan selalu ditentukan dari `{code}` di URL.

**Sukses — `201 Created`** — bentuk sama seperti detail tiket di atas.

**Error — `422`** — pesan per field: `"Kategori tiket wajib diisi."`,
`"Kategori tiket tidak valid."`, `"Judul tiket wajib diisi."`,
`"Deskripsi tiket wajib diisi."`.

**Error — `404`** — kalau `{code}` bukan layanan milik pelanggan login
(dicek SEBELUM validasi body, jadi tidak bisa dipakai menebak apakah
suatu kode layanan itu benar-benar ada).

### `GET /tickets/{code}`

Detail satu tiket lewat kode tiketnya langsung (bukan lewat layanan).
Tiket yang tidak ada, atau ada tapi layanannya bukan milik pelanggan
login → `404`.

## 9. Panduan Uji Coba di Postman

Belum ada file Postman Collection siap-import untuk v1 — ikuti langkah
manual ini (5 menit):

1. **Buat Environment baru** di Postman (ikon mata di kanan atas), isi
   dua variable:
   - `base_url` = `http://nexa.xplus.test:8080/api/v1`
   - `token` = *(kosongkan dulu)*
2. **Request 1 — Minta OTP**: `POST {{base_url}}/auth/otp/request`, tab
   Headers tambah `Accept: application/json`, tab Body pilih `raw`+`JSON`:
   ```json
   { "phone": "81234567890" }
   ```
   Kirim. Salin `verification_token` dari response.
3. **Baca kode OTP**: kalau `WHATSAPP_GATEWAY_DRIVER` di `.env` dev masih
   `log`, buka `storage/logs/laravel.log`, cari baris terbaru
   `[WhatsApp OTP - LOG DRIVER]` — kodenya ada di field `code`. Kalau
   sudah pakai driver `http` (gateway WhatsApp sungguhan), kode akan
   masuk ke WhatsApp nomor yang didaftarkan.
4. **Request 2 — Verifikasi**: `POST {{base_url}}/auth/otp/verify`,
   header sama, body:
   ```json
   { "verification_token": "<hasil langkah 2>", "code": "<kode dari langkah 3>" }
   ```
   Di tab **Tests** request ini, tempel script berikut supaya token
   otomatis tersimpan ke Environment tiap kali request ini berhasil,
   tidak perlu copy-paste manual:
   ```javascript
   if (pm.response.code === 200) {
       pm.environment.set("token", pm.response.json().token);
   }
   ```
   Kirim.
5. **Request selanjutnya** (mis. `GET {{base_url}}/me`) — di tab
   Headers tambah:
   ```
   Accept: application/json
   Authorization: Bearer {{token}}
   ```
   Karena `token` sudah tersimpan di Environment (langkah 4), variable
   `{{token}}` otomatis terisi tanpa perlu diketik ulang.
6. Ulangi pola header yang sama untuk endpoint lain (`GET /services`,
   `GET /services/{code}/invoices`, `POST /services/{code}/tickets`, dst)
   — cukup ganti method/path/body sesuai bagian 8 di atas.

> **Untuk mencoba registrasi pelanggan baru** (bagian 5), polanya sama
> persis — ganti request 1 & 2 di atas dengan `POST /auth/register/request-otp`
> dan `POST /auth/register`, dan tab Tests di request 2 pakai
> `pm.environment.set("token", pm.response.json().token);` juga (bentuk
> response-nya identik `/auth/otp/verify`).

## 10. Catatan untuk Pengembang Aplikasi Pelanggan

Batasan v1 yang perlu diketahui sebelum mulai desain aplikasi pelanggan
(Android/iOS/Web) — sengaja belum dibangun, bukan lupa:

- **Tidak ada pagination** di `GET /services`, `GET /services/{code}/invoices`,
  `GET /services/{code}/tickets` — semua data dikembalikan sekaligus.
  Cukup untuk jumlah data per pelanggan yang realistis kecil saat ini.
- **Token Sanctum tanpa masa kedaluwarsa** dan **full abilities** (tidak
  ada scope per-endpoint) — token berlaku sampai pelanggan logout
  eksplisit. Bisa diperketat nanti (expiry, refresh token, scope) kalau
  ada kebutuhan nyata dari aplikasi sungguhan.
- **Tidak ada endpoint update profil** — v1 murni baca data + lihat/buat
  tiket. Perubahan data pelanggan (nama, email, dst) masih lewat staff di
  admin NEXA.
- **Tidak ada endpoint lengkapi NIK/foto KTP setelah daftar** — akun hasil
  `POST /auth/register` selalu `nik`/`gender`/`birth_date` bernilai `null`
  selamanya sampai staff melengkapi manual lewat admin NEXA. Kalau
  aplikasi pelanggan nanti butuh alur "lengkapi KYC" sendiri, itu endpoint
  baru yang belum ada di v1.
- **Tidak ada push notification** — aplikasi pelanggan perlu polling
  (mis. refresh berkala) untuk tahu update status layanan/tiket, bukan
  push real-time.
- **CORS belum dikonfigurasi khusus** (masih default `config/cors.php`
  Laravel) — kalau aplikasi pelanggan berupa web app dari domain
  terpisah, verifikasi & sesuaikan konfigurasi CORS dulu sebelum
  produksi.

Untuk detail keputusan arsitektur di balik desain di atas (kenapa Sanctum,
kenapa `verification_token` opaque, kenapa 404 bukan 403, dst), lihat
`CLAUDE.md` section **"API Customer-Facing (`/api/v1`)"**.
