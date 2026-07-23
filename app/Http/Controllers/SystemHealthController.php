<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function ready(SystemHealthService $health): JsonResponse
    {
        $ready = $health->isReady();

        return response()->json(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503);
    }

    public function index(SystemHealthService $health): View
    {
        Gate::authorize('system.health');

        return view('admin.system-health', ['checks' => $health->checks()]);
    }
}
