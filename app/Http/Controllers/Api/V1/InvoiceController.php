<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function index(Request $request, string $code): AnonymousResourceCollection
    {
        $service = $request->user()->services()->where('code', $code)->firstOrFail();

        $sales = $service->sales()->with('receipt')->latest()->get();

        return InvoiceResource::collection($sales);
    }

    public function show(Request $request, string $code, string $saleCode): InvoiceResource
    {
        $service = $request->user()->services()->where('code', $code)->firstOrFail();

        $sale = $service->sales()->with('receipt')->where('code', $saleCode)->firstOrFail();

        return new InvoiceResource($sale);
    }
}
