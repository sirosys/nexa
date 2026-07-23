<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteOwnKycRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Lengkapi NIK & foto KTP untuk akun sendiri — akun hasil registrasi
     * mandiri (/auth/register/*) sengaja "daftar ringan dulu" tanpa
     * keduanya (lihat CLAUDE.md "API Customer-Facing"), endpoint ini
     * menyusul jalur pelengkapannya lewat API, reuse UserService::completeKyc()
     * yang sama dipakai admin.
     */
    public function completeKyc(CompleteOwnKycRequest $request): UserResource
    {
        $user = $this->userService->completeKyc($request->user(), $request->validated('nik'), $request->file('ktp_photo'));

        return new UserResource($user);
    }
}
