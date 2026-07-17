<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoverageRequest;
use App\Models\Coverage;
use App\Models\Site;
use App\Services\CoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoverageController extends Controller
{
    public function __construct(private readonly CoverageService $coverageService)
    {
        $this->authorizeResource(Coverage::class, 'coverage');
    }

    public function index(Request $request): View
    {
        $coverages = Coverage::query()
            ->with('site')
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('coverages.index', ['coverages' => $coverages, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('coverages.create', ['sites' => Site::orderBy('name')->get()]);
    }

    public function store(CoverageRequest $request): RedirectResponse
    {
        $this->coverageService->create($request->validated());

        return redirect()->route('coverages.index')->with('status', 'Coverage berhasil ditambahkan.');
    }

    public function show(Coverage $coverage): View
    {
        $coverage->load('site');

        return view('coverages.show', ['coverage' => $coverage]);
    }

    public function edit(Coverage $coverage): View
    {
        return view('coverages.edit', ['coverage' => $coverage, 'sites' => Site::orderBy('name')->get()]);
    }

    public function update(CoverageRequest $request, Coverage $coverage): RedirectResponse
    {
        $this->coverageService->update($coverage, $request->validated());

        return redirect()->route('coverages.index')->with('status', 'Coverage berhasil diperbarui.');
    }

    public function destroy(Coverage $coverage): RedirectResponse
    {
        $this->coverageService->delete($coverage);

        return redirect()->route('coverages.index')->with('status', 'Coverage berhasil dihapus.');
    }
}
