<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Models\Site;
use App\Services\SiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __construct(private readonly SiteService $siteService)
    {
        $this->authorizeResource(Site::class, 'site');
    }

    public function index(Request $request): View
    {
        $sites = Site::query()
            ->with('subdistrict')
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

        return view('sites.index', ['sites' => $sites, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('sites.create');
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $this->siteService->create($request->validated());

        return redirect()->route('sites.index')->with('status', 'Site berhasil ditambahkan.');
    }

    public function show(Site $site): View
    {
        $site->load(['subdistrict', 'coverages']);

        return view('sites.show', ['site' => $site]);
    }

    public function edit(Site $site): View
    {
        $site->load('subdistrict');

        return view('sites.edit', ['site' => $site]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $this->siteService->update($site, $request->validated());

        return redirect()->route('sites.index')->with('status', 'Site berhasil diperbarui.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->siteService->delete($site);

        return redirect()->route('sites.index')->with('status', 'Site berhasil dihapus.');
    }
}
