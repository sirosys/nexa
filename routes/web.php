<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\KtpPhotoController;
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
    Route::resource('users', UserController::class)->except('show');
    Route::get('/secure/ktp/{user}', [KtpPhotoController::class, 'show'])->name('secure.ktp');
});
