<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreServiceTicketRequest;
use App\Http\Resources\Api\V1\ServiceTicketResource;
use App\Models\ServiceTicket;
use App\Services\ServiceTicketService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceTicketController extends Controller
{
    public function __construct(private readonly ServiceTicketService $serviceTicketService) {}

    public function index(Request $request, string $code): AnonymousResourceCollection
    {
        $service = $request->user()->services()->where('code', $code)->firstOrFail();

        $tickets = ServiceTicket::where('service_id', $service->id)->latest()->get();

        return ServiceTicketResource::collection($tickets);
    }

    public function store(Request $request, string $code, StoreServiceTicketRequest $storeRequest): ServiceTicketResource
    {
        // Service discope dulu (404 kalau bukan milik sendiri) SEBELUM
        // authorize — pola scoping-before-policy, lihat CLAUDE.md "API
        // Customer-Facing".
        $service = $request->user()->services()->where('code', $code)->firstOrFail();

        $this->authorize('create', [ServiceTicket::class, $service]);

        $ticket = $this->serviceTicketService->create([
            ...$storeRequest->validated(),
            'service_id' => $service->id,
        ]);

        return new ServiceTicketResource($ticket);
    }

    public function show(Request $request, string $code): ServiceTicketResource
    {
        $ticket = ServiceTicket::whereHas(
            'service',
            fn ($query) => $query->where('user_id', $request->user()->id)
        )->where('code', $code)->firstOrFail();

        return new ServiceTicketResource($ticket);
    }
}
