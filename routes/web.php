<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Shell login + verifikasi OTP — placeholder, lihat App\Http\Controllers\Auth\LoginController.
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'sendOtp'])->name('login.send');
Route::get('/login/otp', [LoginController::class, 'showOtp'])->name('login.otp');
Route::post('/login/otp', [LoginController::class, 'verifyOtp'])->name('login.otp.verify');

// Placeholder dashboard — belum ada middleware auth sampai session guard sungguhan dibangun.
Route::view('/dashboard', 'dashboard')->name('dashboard');
