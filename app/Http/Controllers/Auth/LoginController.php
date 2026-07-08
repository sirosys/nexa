<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placeholder untuk alur login phone + OTP WhatsApp (lihat README.md).
 * Belum ada validasi, pengiriman OTP, maupun session auth guard sungguhan —
 * hanya cukup untuk menavigasi shell login -> verifikasi OTP -> dashboard.
 */
class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        return redirect()->route('login.otp');
    }

    public function showOtp(): View
    {
        return view('auth.verify-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        return redirect()->route('dashboard');
    }
}
