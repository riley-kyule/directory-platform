<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationContent;
use App\Models\PageContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only SEO health checks: pages that exist but nothing on the site
 * links to (relying on the sitemap alone to be discovered), and pages that
 * would compete with each other for the same search intent.
 */
class SeoAuditService
{
    private const MIN_TITLE_LENGTH = 30;

    private const MAX_TITLE_LENGTH = 60;

    private const MIN_META_DESCRIPTION_LENGTH = 120;

    private const MAX_META_DESCRIPTION_LENGTH = 160;

    private const MIN_CONTENT_WORDS = 150;

    private const STALE_AFTER_DAYS = 180;

    public function __construct(private readonly DirectorySettings $settings) {}

    /**
     * A published location with no active profile has no page anywhere on
     * the site linking to it — active_profile_count is exactly the count of
     * profiles whose breadcrumb chain includes this location, at any depth,
     * so zero means genuinely no inbound internal link.
     *
     * @return Collection<int, Location>
     */
    public function orphanLocations(): Collection
    {
        return Location::query()
            ->where('status', 'published')
            ->where('active_profile_count', 0)
            ->with('parent')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    public function duplicateSeoTitles(): Collection
    {
        return $this->duplicatesOf('seo_title');
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    public function duplicateMetaDescriptions(): Collection
    {
        return $this->duplicatesOf('meta_description');
    }

    /**
     * Published location pages that are technically available but have an SEO,
     * content, freshness, or indexability defect that deserves editorial action.
     *
     * @return Collection<int, array{location: Location, issues: list<string>, severity: string}>
     */
    public function locationQualityIssues(): Collection
    {
        return Location::query()
            ->where('status', 'published')
            ->with(['content', 'parent'])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (Location $location): array {
                $content = $location->content;
                $issues = [];
                $critical = false;

                if (! $content) {
                    $issues[] = 'No editable content record exists.';
                    $critical = true;
                } elseif ($content->content_status !== 'approved') {
                    $issues[] = 'Content is not approved for publication.';
                    $critical = true;
                }

                $minimumProfiles = $location->isMicroLocation()
                    ? $this->settings->integer('locations.micro_min_profiles')
                    : 1;
                $shouldBeIndexable = $content?->content_status === 'approved'
                    && $location->active_profile_count >= $minimumProfiles;

                if ($location->is_indexable !== $shouldBeIndexable) {
                    $issues[] = $shouldBeIndexable
                        ? 'Eligible for indexing but currently marked noindex.'
                        : 'Marked indexable without enough inventory and approved content.';
                    $critical = true;
                }

                if ($content) {
                    $this->appendMetadataIssues($issues, $content->seo_title, $content->meta_description);

                    if ($this->wordCount($content->intro_content.' '.$content->bottom_content) < self::MIN_CONTENT_WORDS) {
                        $issues[] = 'Thin location copy: fewer than '.self::MIN_CONTENT_WORDS.' words across the intro and supporting content.';
                    }

                    if (! $content->last_reviewed_at || $content->last_reviewed_at->lte(now()->subDays(self::STALE_AFTER_DAYS))) {
                        $issues[] = 'Content has not been reviewed in the last '.self::STALE_AFTER_DAYS.' days.';
                    }
                }

                return [
                    'location' => $location,
                    'issues' => $issues,
                    'severity' => $critical ? 'critical' : 'warning',
                ];
            })
            ->filter(fn (array $result) => $result['issues'] !== [])
            ->values();
    }

    /** @return Collection<int, array{page: PageContent, label: string, issues: list<string>} > */
    public function pageQualityIssues(): Collection
    {
        return PageContent::query()
            ->whereIn('page_key', ['homepage', 'agencies'])
            ->orderBy('page_key')
            ->get()
            ->map(function (PageContent $page): array {
                $issues = [];
                $this->appendMetadataIssues($issues, $page->seo_title, $page->meta_description);

                if ($this->wordCount($page->intro_content.' '.$page->bottom_content) < self::MIN_CONTENT_WORDS) {
                    $issues[] = 'Thin page copy: fewer than '.self::MIN_CONTENT_WORDS.' words across the intro and supporting content.';
                }

                return [
                    'page' => $page,
                    'label' => $page->page_key === 'homepage' ? 'Homepage' : 'Agency directory',
                    'issues' => $issues,
                ];
            })
            ->filter(fn (array $result) => $result['issues'] !== [])
            ->values();
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    private function duplicatesOf(string $column): Collection
    {
        $duplicateValues = LocationContent::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->having('total', '>', 1)
            ->pluck($column);

        if ($duplicateValues->isEmpty()) {
            return collect();
        }

        return LocationContent::query()
            ->whereIn($column, $duplicateValues)
            ->with('location:id,name,full_slug,status')
            ->get()
            ->groupBy($column);
    }

    /** @param list<string> $issues */
    private function appendMetadataIssues(array &$issues, ?string $title, ?string $description): void
    {
        $titleLength = Str::length(trim((string) $title));
        if ($titleLength < self::MIN_TITLE_LENGTH || $titleLength > self::MAX_TITLE_LENGTH) {
            $issues[] = 'SEO title is '.$titleLength.' characters; target '.self::MIN_TITLE_LENGTH.'–'.self::MAX_TITLE_LENGTH.'.';
        }

        $descriptionLength = Str::length(trim((string) $description));
        if ($descriptionLength < self::MIN_META_DESCRIPTION_LENGTH || $descriptionLength > self::MAX_META_DESCRIPTION_LENGTH) {
            $issues[] = 'Meta description is '.$descriptionLength.' characters; target '.self::MIN_META_DESCRIPTION_LENGTH.'–'.self::MAX_META_DESCRIPTION_LENGTH.'.';
        }
    }

    private function wordCount(?string $content): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags((string) $content), $words);

        return count($words[0]);
    }
}
