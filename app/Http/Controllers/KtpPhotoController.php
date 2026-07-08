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

        // Pemilik akun sendiri, atau admin/staff (belum ada tingkatan
        // superadmin terpisah — lihat CLAUDE.md bagian "Foto KTP").
        abort_unless($viewer->id === $user->id || $viewer->isAdmin(), 403);

        $path = $user->userDetails?->ktp_photo;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
