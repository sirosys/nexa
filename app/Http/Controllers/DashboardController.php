<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): View
    {
        return view('dashboard', [
            'stats' => $this->dashboardService->stats(),
            'statusDistribution' => $this->dashboardService->serviceStatusDistribution(),
            'monthlyRevenue' => $this->dashboardService->monthlyRevenue(),
            'recentServices' => $this->dashboardService->recentServices(),
        ]);
    }
}
