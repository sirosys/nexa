<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceTicketController;
use Illuminate\Support\Facades\Route;

// API customer-facing v1 — lihat CLAUDE.md "API Customer-Facing (/api/v1)".
// Prefix `api/v1` otomatis lewat apiPrefix di bootstrap/app.php, tidak
// perlu Route::prefix() manual di sini. Auth pakai Sanctum Bearer token,
// bukan session — role `customer` khusus (alur OTP admin di routes/web.php
// menolak role ini, alur OTP di sini justru HANYA menerima role ini).
Route::post('/auth/otp/request', [OtpController::class, 'request'])->middleware('throttle:api-otp-request');
Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:api-otp-verify');

// Registrasi mandiri pelanggan — TERPISAH dari pasangan route di atas
// (itu untuk login nomor yang SUDAH terdaftar; ini untuk nomor BARU).
Route::post('/auth/register/request-otp', [RegistrationController::class, 'requestOtp'])->middleware('throttle:api-register-otp-request');
Route::post('/auth/register', [RegistrationController::class, 'register'])->middleware('throttle:api-register');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'logout']);
    Route::get('/me', [ProfileController::class, 'show']);
    Route::post('/me/complete-kyc', [ProfileController::class, 'completeKyc'])->middleware('throttle:api-kyc');

    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{code}', [ServiceController::class, 'show']);
    Route::get('/services/{code}/invoices', [InvoiceController::class, 'index']);
    Route::get('/services/{code}/invoices/{saleCode}', [InvoiceController::class, 'show']);
    Route::get('/services/{code}/tickets', [ServiceTicketController::class, 'index']);
    Route::post('/services/{code}/tickets', [ServiceTicketController::class, 'store']);
    Route::get('/tickets/{code}', [ServiceTicketController::class, 'show']);
});
