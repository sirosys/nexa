<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstallationPhotoController extends Controller
{
    public function show(Request $request, Service $service): StreamedResponse
    {
        $viewer = $request->user();

        // Superadmin, atau teknisi yang jadi installer service ini — pola
        // sama KtpPhotoController, lihat CLAUDE.md "Installation".
        abort_unless(
            $viewer->isSuperadmin() || ($viewer->isTechnician() && $service->activation?->installer_id === $viewer->id),
            403
        );

        $path = $service->activation?->photo;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
