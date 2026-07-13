<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): View
    {
        $user = Auth::user();

        return view('dashboard', [
            'stats' => $this->dashboardService->stats($user),
            'statusDistribution' => $user->can('services.view') ? $this->dashboardService->serviceStatusDistribution() : null,
            'monthlyRevenue' => $user->can('sales.view') ? $this->dashboardService->monthlyRevenue() : null,
            'recentServices' => $user->can('services.view') ? $this->dashboardService->recentServices() : null,
        ]);
    }
}
