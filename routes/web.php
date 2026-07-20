<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DismantleController;
use App\Http\Controllers\DismantlePhotoController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\InstallationPhotoController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\KtpPhotoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SubdistrictController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\Webhooks\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Di luar group guest/auth sengaja — dipanggil server-ke-server oleh
// Xendit, bukan browser pengguna, jadi tidak boleh butuh session/CSRF
// (lihat pengecualian CSRF di bootstrap/app.php dan verifikasi
// x-callback-token di dalam controller-nya).
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle'])->name('webhooks.xendit');

// Halaman publik pilih channel pembayaran - diakses pelanggan lewat link
// di InvoiceCreatedNotification (WhatsApp), TANPA login (aplikasi
// customer-facing belum dibangun, lihat CLAUDE.md "Authentication/Login").
// Diamankan lewat Laravel signed URL (middleware `signed`), bukan sesi
// auth, karena tidak ada akun customer yang bisa dipakai login ke NEXA.
// POST tunggal (update()) menangani kirim-ulang OTP, verifikasi OTP, dan
// pilih channel sekaligus - lihat docblock PaymentController kenapa tidak
// dipecah jadi route terpisah per aksi (soal signed URL).
Route::middleware('signed')->group(function () {
    Route::get('/pay/{receipt}', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/pay/{receipt}', [PaymentController::class, 'update'])
        ->middleware('throttle:payment-action')
        ->name('payment.update');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'sendOtp'])
        ->middleware('throttle:otp-request')
        ->name('login.send');
    Route::get('/login/otp', [LoginController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/otp', [LoginController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')
        ->name('login.otp.verify');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/users/{user}/complete-kyc', [UserController::class, 'completeKyc'])->name('users.complete-kyc');
    // 'create' sengaja tidak dipakai — "Tambah Pengguna" sekarang modal di
    // halaman index (lihat users/index.blade.php), bukan halaman terpisah.
    Route::resource('users', UserController::class)->except(['create']);
    Route::get('/secure/ktp/{user}', [KtpPhotoController::class, 'show'])->name('secure.ktp');
    Route::resource('plans', PlanController::class);
    Route::resource('products', ProductController::class);
    Route::resource('packages', PackageController::class);
    Route::get('/subdistricts/search', [SubdistrictController::class, 'search'])->name('subdistricts.search');
    Route::resource('sites', SiteController::class);
    Route::resource('coverages', CoverageController::class);
    Route::get('/services/customers/search', [ServiceController::class, 'searchCustomers'])->name('services.customers.search');
    Route::post('/services/customers', [ServiceController::class, 'storeCustomer'])->name('services.customers.store');
    // 'create' sengaja tidak dipakai — "Tambah Service" sekarang modal wizard
    // di halaman index (lihat services/index.blade.php + _wizard.blade.php),
    // bukan halaman terpisah. Pola sama users.store, lihat CLAUDE.md "User".
    Route::resource('services', ServiceController::class)->except(['create']);
    Route::get('/sales/services/search', [SaleController::class, 'searchServices'])->name('sales.services.search');
    Route::post('/sales/{sale}/receipt/retry', [SaleController::class, 'retryReceipt'])->name('sales.receipt.retry');
    Route::resource('sales', SaleController::class);

    // Modul Installation — bukan CRUD resource standar, route-model-binding
    // di atas Service (bukan model ServiceActivation), lihat CLAUDE.md
    // "Installation".
    Route::get('/installations', [InstallationController::class, 'index'])->name('installations.index');
    Route::get('/installations/{service}', [InstallationController::class, 'show'])->name('installations.show');
    Route::post('/installations/{service}/assign', [InstallationController::class, 'assign'])->name('installations.assign');
    Route::post('/installations/{service}/claim', [InstallationController::class, 'claim'])->name('installations.claim');
    Route::post('/installations/{service}/complete', [InstallationController::class, 'complete'])->name('installations.complete');
    Route::get('/secure/installation-photo/{service}', [InstallationPhotoController::class, 'show'])->name('secure.installation-photo');

    // Modul Dismantle — bukan CRUD resource standar, route-model-binding
    // di atas Service (bukan model ServiceDismantle), lihat CLAUDE.md
    // "Dismantle".
    Route::get('/dismantles', [DismantleController::class, 'index'])->name('dismantles.index');
    Route::get('/dismantles/{service}', [DismantleController::class, 'show'])->name('dismantles.show');
    Route::post('/dismantles/{service}/queue', [DismantleController::class, 'queue'])->name('dismantles.queue');
    Route::post('/dismantles/{service}/assign', [DismantleController::class, 'assign'])->name('dismantles.assign');
    Route::post('/dismantles/{service}/claim', [DismantleController::class, 'claim'])->name('dismantles.claim');
    Route::post('/dismantles/{service}/complete', [DismantleController::class, 'complete'])->name('dismantles.complete');
    Route::get('/secure/dismantle-photo/{service}', [DismantlePhotoController::class, 'show'])->name('secure.dismantle-photo');

    // Modul Ticketing — lihat CLAUDE.md "Ticketing". Resource CRUD biasa
    // (beda dari Installation/Dismantle yang route-model-binding di atas
    // Service) plus 3 action non-resource untuk assign/klaim/selesaikan.
    Route::get('/tickets/services/search', [ServiceTicketController::class, 'searchServices'])->name('tickets.services.search');
    Route::post('/tickets/{ticket}/assign', [ServiceTicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/claim', [ServiceTicketController::class, 'claim'])->name('tickets.claim');
    Route::post('/tickets/{ticket}/resolve', [ServiceTicketController::class, 'resolve'])->name('tickets.resolve');
    Route::resource('tickets', ServiceTicketController::class);

    // Modul Inventaris — lihat CLAUDE.md "Inventaris". Tidak ada
    // edit/update (product_id/is_serialized immutable setelah dibuat,
    // tidak ada field lain yang masuk akal diedit lewat form biasa).
    // Parameter route dipaksa {item} (bukan default {inventory_item})
    // supaya konsisten dengan nama parameter di InventoryItemController.
    Route::resource('inventory-items', InventoryItemController::class)
        ->except(['edit', 'update'])
        ->parameters(['inventory-items' => 'item']);
    Route::post('/inventory-items/{item}/stock-in', [InventoryItemController::class, 'stockIn'])->name('inventory-items.stock-in');
    Route::post('/inventory-items/{item}/adjust', [InventoryItemController::class, 'adjust'])->name('inventory-items.adjust');

    // Modul Vendor & Supplier — lihat CLAUDE.md "Vendor & Supplier". Vendor
    // resource CRUD biasa; Purchase Order resource CRUD plus 3 action
    // non-resource (order/receive/cancel) untuk transisi status.
    Route::resource('vendors', VendorController::class);
    Route::post('/purchase-orders/{purchase_order}/order', [PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::resource('purchase-orders', PurchaseOrderController::class);

    // Modul System Setting — lihat CLAUDE.md "System Setting". Bukan CRUD
    // resource (tidak ada create/delete, katalog key tetap dari seeder),
    // cuma satu halaman view + satu action update untuk seluruh setting
    // sekaligus.
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Modul Audit Log — lihat CLAUDE.md "Audit Log". Read-only, tidak ada
    // create/update/delete lewat UI (append-only, dicatat lewat
    // AuditLogService::record() di titik-titik aksi sensitif).
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Modul Role & Permission Management — lihat CLAUDE.md "Authorization /
    // Role & Permission". reset-to-default didaftarkan sebelum
    // Route::resource supaya tidak tertelan wildcard {role}.
    Route::post('/roles/{role}/reset-to-default', [RoleController::class, 'resetToDefault'])->name('roles.reset-to-default');
    Route::resource('roles', RoleController::class)->except(['show', 'create']);

    // Modul Reporting — lihat CLAUDE.md "Reporting". Murni agregasi
    // read-only lintas modul, tidak melekat ke satu Eloquent model, jadi
    // tanpa Policy class dan tanpa landing page — 4 halaman kategori
    // berdiri sendiri (bukan tab Alpine satu halaman) supaya filter tanggal
    // + paginasi tiap kategori independen lewat query string GET.
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/reports/operations', [ReportController::class, 'operations'])->name('reports.operations');
    Route::get('/reports/customers', [ReportController::class, 'customers'])->name('reports.customers');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
});
