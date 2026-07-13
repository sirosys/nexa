<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KtpPhotoController extends Controller
{
    public function show(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();

        // Pemilik akun sendiri, atau permission users.view-ktp-photo (cuma
        // dipegang superadmin di matrix saat ini — foto KTP tetap PII
        // sensitif yang sengaja tidak dibuka lewat users.view biasa, lihat
        // CLAUDE.md "Authorization / Role & Permission").
        abort_unless($viewer->id === $user->id || $viewer->can('users.view-ktp-photo'), 403);

        $path = $user->userDetails?->ktp_photo;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
