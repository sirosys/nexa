<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\OtpThrottledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SendCustomerOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyCustomerOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Alur OTP untuk aplikasi customer-facing (`/api/v1`) — TERPISAH dari
 * App\Http\Controllers\Auth\LoginController (admin), yang justru menolak
 * role `customer`. Reuse OtpService apa adanya (state OTP tetap di tabel
 * `otp_codes`), tapi API stateless jadi tidak bisa pakai session seperti
 * admin — sesi verifikasi ditandai lewat `verification_token` opaque yang
 * disimpan singkat di Cache (lihat CLAUDE.md "API Customer-Facing").
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function request(SendCustomerOtpRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->validated('phone'))->firstOrFail();

        try {
            $this->otpService->requestOtp($user);
        } catch (OtpThrottledException) {
            return response()->json([
                'message' => 'Mohon tunggu sebentar sebelum meminta kode baru.',
            ], 429);
        }

        $verificationToken = Str::random(64);

        Cache::put(
            $this->challengeKey($verificationToken),
            $user->id,
            now()->addMinutes((int) config('otp.ttl_minutes')),
        );

        return response()->json([
            'message' => 'Kode OTP telah dikirim lewat WhatsApp.',
            'verification_token' => $verificationToken,
        ], 202);
    }

    public function verify(VerifyCustomerOtpRequest $request): JsonResponse
    {
        $userId = Cache::get($this->challengeKey($request->validated('verification_token')));

        if (! $userId) {
            return response()->json([
                'message' => 'Sesi verifikasi tidak valid atau sudah kedaluwarsa.',
            ], 422);
        }

        $user = User::find($userId);

        // Jaga-jaga kalau role user berubah di antara request & verify —
        // pola sama guard kedua LoginController::verifyOtp() (admin).
        if (! $user || ! $user->isCustomer()) {
            Cache::forget($this->challengeKey($request->validated('verification_token')));

            return response()->json([
                'message' => 'Sesi verifikasi tidak valid atau sudah kedaluwarsa.',
            ], 422);
        }

        if (! $this->otpService->verify($user, $request->validated('code'))) {
            return response()->json([
                'message' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ], 422);
        }

        Cache::forget($this->challengeKey($request->validated('verification_token')));

        return response()->json([
            'token' => $user->createToken('customer-api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    private function challengeKey(string $token): string
    {
        return "otp:challenge:{$token}";
    }
}
