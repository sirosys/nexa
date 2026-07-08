<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OtpThrottledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function sendOtp(SendOtpRequest $request): RedirectResponse
    {
        $user = User::where('phone', $request->validated('phone'))->firstOrFail();

        try {
            $this->otpService->requestOtp($user);
        } catch (OtpThrottledException) {
            return back()->withErrors([
                'phone' => 'Mohon tunggu sebentar sebelum meminta kode baru.',
            ])->withInput();
        }

        $request->session()->put('otp.user_id', $user->id);

        return redirect()->route('login.otp');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp.user_id')) {
            return redirect()->route('login')->withErrors([
                'phone' => 'Sesi verifikasi tidak ditemukan, silakan masuk ulang.',
            ]);
        }

        return view('auth.verify-otp');
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $user = User::find($request->session()->get('otp.user_id'));

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'phone' => 'Sesi verifikasi tidak ditemukan, silakan masuk ulang.',
            ]);
        }

        if (! $this->otpService->verify($user, $request->validated('code'))) {
            return back()->withErrors([
                'code' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ]);
        }

        $request->session()->forget('otp.user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
