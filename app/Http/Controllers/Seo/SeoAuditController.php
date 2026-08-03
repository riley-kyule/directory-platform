<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\SeoAuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeoAuditController extends Controller
{
    public function __construct(private readonly SeoAuditService $audit) {}

    public function index(): View
    {
        Gate::authorize('seo.metadata');

        return view('seo.audit.index', [
            'orphanLocations' => $this->audit->orphanLocations(),
            'duplicateSeoTitles' => $this->audit->duplicateSeoTitles(),
            'duplicateMetaDescriptions' => $this->audit->duplicateMetaDescriptions(),
        ]);
    }
}
