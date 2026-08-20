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

        $orphanLocations = $this->audit->orphanLocations();
        $locationQualityIssues = $this->audit->locationQualityIssues();
        $pageQualityIssues = $this->audit->pageQualityIssues();
        $duplicateSeoTitles = $this->audit->duplicateSeoTitles();
        $duplicateMetaDescriptions = $this->audit->duplicateMetaDescriptions();

        return view('seo.audit.index', [
            'orphanLocations' => $orphanLocations,
            'locationQualityIssues' => $locationQualityIssues,
            'pageQualityIssues' => $pageQualityIssues,
            'duplicateSeoTitles' => $duplicateSeoTitles,
            'duplicateMetaDescriptions' => $duplicateMetaDescriptions,
            'auditIssueCount' => $orphanLocations->count()
                + $locationQualityIssues->count()
                + $pageQualityIssues->count()
                + $duplicateSeoTitles->count()
                + $duplicateMetaDescriptions->count(),
        ]);
    }
}
