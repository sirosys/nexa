<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DismantlePhotoController extends Controller
{
    public function show(Request $request, Service $service): StreamedResponse
    {
        $viewer = $request->user();

        // Superadmin, atau teknisi yang jadi technician dismantle ini —
        // pola sama InstallationPhotoController, lihat CLAUDE.md "Dismantle".
        abort_unless(
            $viewer->isSuperadmin() || ($viewer->isTechnician() && $service->dismantle?->technician_id === $viewer->id),
            403
        );

        $path = $service->dismantle?->photo;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
