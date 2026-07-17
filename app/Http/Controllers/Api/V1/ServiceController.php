<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $services = $request->user()->services()
            ->with(['coverage', 'package'])
            ->latest()
            ->get();

        return ServiceResource::collection($services);
    }

    /**
     * `$code` sengaja parameter string biasa, BUKAN implicit route-model
     * binding — query selalu discope lewat relasi user() supaya salah/
     * service milik orang lain jadi 404 natural, bukan cabang otorisasi
     * terpisah (lihat CLAUDE.md "API Customer-Facing").
     */
    public function show(Request $request, string $code): ServiceResource
    {
        $service = $request->user()->services()
            ->with(['coverage', 'package'])
            ->where('code', $code)
            ->firstOrFail();

        return new ServiceResource($service);
    }
}
