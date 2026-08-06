<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function index(): View
    {
        Gate::authorize('audit.view');

        return view('admin.dashboard', [
            'metrics' => $this->metrics->summary(),
        ]);
    }
}
