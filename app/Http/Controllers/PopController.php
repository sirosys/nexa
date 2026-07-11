<?php

namespace App\Http\Controllers;

use App\Http\Requests\PopRequest;
use App\Models\Pop;
use App\Services\PopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PopController extends Controller
{
    public function __construct(private readonly PopService $popService)
    {
        $this->authorizeResource(Pop::class, 'pop');
    }

    public function index(Request $request): View
    {
        $pops = Pop::query()
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

        return view('pops.index', ['pops' => $pops, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('pops.create');
    }

    public function store(PopRequest $request): RedirectResponse
    {
        $this->popService->create($request->validated());

        return redirect()->route('pops.index')->with('status', 'PoP berhasil ditambahkan.');
    }

    public function show(Pop $pop): View
    {
        $pop->load(['subdistrict', 'coverages']);

        return view('pops.show', ['pop' => $pop]);
    }

    public function edit(Pop $pop): View
    {
        $pop->load('subdistrict');

        return view('pops.edit', ['pop' => $pop]);
    }

    public function update(PopRequest $request, Pop $pop): RedirectResponse
    {
        $this->popService->update($pop, $request->validated());

        return redirect()->route('pops.index')->with('status', 'PoP berhasil diperbarui.');
    }

    public function destroy(Pop $pop): RedirectResponse
    {
        $this->popService->delete($pop);

        return redirect()->route('pops.index')->with('status', 'PoP berhasil dihapus.');
    }
}
