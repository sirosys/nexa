<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Modul Reporting — murni agregasi read-only lintas modul, tidak melekat ke
 * satu Eloquent model, jadi tidak ada Policy class (pola sama
 * SubdistrictController::search()). Gate lewat abort_unless() inline di tiap
 * method, satu permission reports.view (superadmin-only untuk v1) — lihat
 * CLAUDE.md "Reporting".
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function finance(Request $request): View
    {
        abort_unless(Auth::user()->can('reports.view'), 403);

        [$from, $to] = $this->resolveRange($request);

        return view('reports.finance', [
            ...$this->reportService->finance($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function operations(Request $request): View
    {
        abort_unless(Auth::user()->can('reports.view'), 403);

        [$from, $to] = $this->resolveRange($request);

        return view('reports.operations', [
            ...$this->reportService->operations($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function customers(Request $request): View
    {
        abort_unless(Auth::user()->can('reports.view'), 403);

        [$from, $to] = $this->resolveRange($request);

        return view('reports.customers', [
            ...$this->reportService->customers($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Filter tanggal query string from/to (Y-m-d), default awal bulan
     * berjalan s/d hari ini kalau kosong. Validasi ringan supaya input rusak
     * (mis. ?from=bukan-tanggal) tidak menghasilkan 500 — redirect balik
     * dengan error, bukan crash.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
