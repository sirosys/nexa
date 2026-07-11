<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\KtpPhotoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubdistrictController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::resource('users', UserController::class)->except('show');
    Route::get('/secure/ktp/{user}', [KtpPhotoController::class, 'show'])->name('secure.ktp');
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('packages', PackageController::class)->except('show');
    Route::get('/subdistricts/search', [SubdistrictController::class, 'search'])->name('subdistricts.search');
    Route::resource('pops', PopController::class)->except('show');
    Route::resource('coverages', CoverageController::class)->except('show');
    Route::get('/services/customers/search', [ServiceController::class, 'searchCustomers'])->name('services.customers.search');
    Route::resource('services', ServiceController::class)->except('show');
    Route::get('/sales/services/search', [SaleController::class, 'searchServices'])->name('sales.services.search');
    Route::resource('sales', SaleController::class)->except('show');
});
