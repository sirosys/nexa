<?php

namespace App\Http\Controllers;

use App\Models\Subdistrict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubdistrictController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->isSuperadmin(), 403);

        $q = $request->string('q')->trim()->value();

        if ($q === '') {
            return response()->json([]);
        }

        $subdistricts = Subdistrict::query()
            ->where('name', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'district_name', 'city_name', 'province_name']);

        return response()->json($subdistricts);
    }
}
