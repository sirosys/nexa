<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $planService)
    {
        $this->authorizeResource(Plan::class, 'plan');
    }

    public function index(Request $request): View
    {
        $plans = Plan::query()
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

        return view('plans.index', ['plans' => $plans, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('plans.create');
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $this->planService->create($request->validated());

        return redirect()->route('plans.index')->with('status', 'Plan berhasil ditambahkan.');
    }

    public function show(Plan $plan): View
    {
        return view('plans.show', ['plan' => $plan]);
    }

    public function edit(Plan $plan): View
    {
        return view('plans.edit', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->planService->update($plan, $request->validated());

        return redirect()->route('plans.index')->with('status', 'Plan berhasil diperbarui.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->planService->delete($plan);

        return redirect()->route('plans.index')->with('status', 'Plan berhasil dihapus.');
    }
}
