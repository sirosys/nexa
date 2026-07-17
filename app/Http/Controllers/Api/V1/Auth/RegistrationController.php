<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\OtpThrottledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequestOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\RegistrationOtpService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * Registrasi mandiri pelanggan lewat aplikasi (lihat CLAUDE.md "API
 * Customer-Facing") — TERPISAH total dari App\Http\Controllers\Api\V1\Auth\OtpController
 * (itu untuk LOGIN nomor yang SUDAH terdaftar). Dua langkah: verifikasi
 * nomor dulu (requestOtp), baru submit data + kode sekaligus (register) —
 * akun `User` baru dijamin sudah dalam keadaan nomor terverifikasi begitu
 * dibuat, tidak pernah ada baris "akun belum terverifikasi" yang menumpuk.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationOtpService $registrationOtpService,
        private readonly UserService $userService,
    ) {}

    public function requestOtp(RegisterRequestOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');

        try {
            $registrationToken = $this->registrationOtpService->requestOtp($phone);
        } catch (OtpThrottledException) {
            return response()->json([
                'message' => 'Mohon tunggu sebentar sebelum meminta kode baru.',
            ], 429);
        }

        return response()->json([
            'message' => 'Kode OTP telah dikirim lewat WhatsApp.',
            'registration_token' => $registrationToken,
        ], 202);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $phone = $this->registrationOtpService->verify(
            $request->validated('registration_token'),
            $request->validated('code'),
        );

        if (! $phone) {
            return response()->json([
                'message' => 'Kode OTP salah, sudah kedaluwarsa, atau token verifikasi tidak valid.',
            ], 422);
        }

        // Guard defensif (race condition) — nomor bisa saja terdaftar oleh
        // jalur lain (mis. dibuat staff lewat admin) tepat di antara
        // requestOtp() dan register() ini. Kode/token yang sudah
        // dikonsumsi TIDAK dikembalikan — pelanggan harus mulai ulang dari
        // requestOtp() kalau ini terjadi (kasus langka, bukan alur normal).
        if (User::where('phone', $phone)->exists()) {
            return response()->json([
                'message' => 'Nomor telepon ini sudah terdaftar. Silakan masuk lewat menu login.',
            ], 422);
        }

        $user = $this->userService->create([
            'name' => $request->validated('name'),
            'phone' => $phone,
            'email' => $request->validated('email'),
            'role' => 'customer',
        ]);

        return response()->json([
            'token' => $user->createToken('customer-api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }
}
