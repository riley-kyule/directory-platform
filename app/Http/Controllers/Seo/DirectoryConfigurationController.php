<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\StoreTaxonomyOptionRequest;
use App\Http\Requests\UpdateAgencyDirectoryContentRequest;
use App\Http\Requests\UpdateHomepageContentRequest;
use App\Http\Requests\UpdateLocationContentRequest;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PageContent;
use App\Models\TaxonomyOption;
use App\Services\LocationInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DirectoryConfigurationController extends Controller
{
    private const PROTECTED_SLUGS = [
        'escort', 'search', 'login', 'register', 'account', 'admin', 'api', 'help',
        'about', 'contact', 'privacy', 'terms', 'safety', 'report', 'media', 'agencies', 'agency',
        'robots-txt', 'robotstxt', 'sitemap-xml', 'sitemapxml', 'sitemaps',
    ];

    public function __construct(private readonly LocationInventoryService $locationInventory) {}

    public function index(): View
    {
        Gate::authorize('seo.locations');

        return view('seo.directory.index', [
            'locations' => Location::query()->with(['parent', 'content'])->orderBy('country_code')->orderBy('full_slug')->get(),
            'taxonomyOptions' => TaxonomyOption::query()->orderBy('type')->orderBy('sort_order')->orderBy('label')->get(),
            'homepage' => PageContent::query()->where('page_key', 'homepage')->firstOrFail(),
            'agencyDirectory' => PageContent::query()->where('page_key', 'agencies')->firstOrFail(),
        ]);
    }

    public function createLocation(): View
    {
        Gate::authorize('seo.locations');

        return view('seo.directory.location-form', [
            'parents' => Location::query()->orderBy('country_code')->orderBy('full_slug')->get(),
        ]);
    }

    public function storeLocation(StoreLocationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $slug = Str::slug($validated['name']);

        if (! $slug || (! ($validated['parent_id'] ?? null) && in_array($slug, self::PROTECTED_SLUGS, true))) {
            return back()->withInput()->withErrors(['name' => 'This name cannot be used as a top-level location URL.']);
        }

        $parent = ! empty($validated['parent_id']) ? Location::query()->findOrFail($validated['parent_id']) : null;
        $fullSlug = $parent ? $parent->full_slug.'/'.$slug : $slug;

        if (Location::query()->where('full_slug', $fullSlug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'A location with this canonical path already exists.']);
        }

        $location = DB::transaction(function () use ($request, $validated, $slug, $fullSlug, $parent): Location {
            $location = Location::query()->create([
                'parent_id' => $parent?->id,
                'country_code' => $validated['country_code'],
                'type' => $validated['type'],
                'name' => $validated['name'],
                'slug' => $slug,
                'full_slug' => $fullSlug,
                'status' => $validated['status'],
                'is_indexable' => false,
            ]);

            DB::table('location_contents')->insert([
                'location_id' => $location->id,
                'heading' => $validated['page_heading'] ?? $location->name.' Escorts',
                'intro_content' => $validated['intro_content'] ?? '',
                'bottom_content' => $validated['bottom_content'] ?? null,
                'faq_content' => ! empty($validated['faq_content']) ? json_encode(['content' => $validated['faq_content']]) : null,
                'seo_title' => $validated['seo_title'] ?? '',
                'meta_description' => $validated['meta_description'] ?? '',
                'canonical_path' => '/'.$fullSlug.'-escorts',
                'content_status' => $validated['status'] === 'published' ? 'approved' : 'draft',
                'last_reviewed_at' => $validated['status'] === 'published' ? now() : null,
                'reviewed_by' => $validated['status'] === 'published' ? $request->user()->id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit($request->user()->id, 'locations.create', $location->id, [
                'name' => $location->name,
                'status' => $location->status,
                'full_slug' => $location->full_slug,
            ]);

            return $location;
        });

        return redirect()->route('seo.directory.index')->with('status', "Location {$location->name} created.");
    }

    public function editLocation(Location $location): View
    {
        Gate::authorize('seo.content');
        abort_unless($location->content, 404);

        return view('seo.directory.location-content-form', ['location' => $location->load(['content', 'parent'])]);
    }

    public function updateLocation(UpdateLocationContentRequest $request, Location $location): RedirectResponse
    {
        abort_unless($location->content, 404);
        $validated = $request->validated();
        $previous = ['location_status' => $location->status]
            + $location->content->only(['heading', 'intro_content', 'bottom_content', 'seo_title', 'meta_description', 'canonical_path']);

        $location->content->update([
            'heading' => $validated['heading'] ?? $location->name.' Escorts',
            'intro_content' => $validated['intro_content'] ?? '',
            'bottom_content' => $validated['bottom_content'] ?? null,
            'faq_content' => ! empty($validated['faq_content']) ? ['content' => $validated['faq_content']] : null,
            'seo_title' => $validated['seo_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
            'canonical_path' => $validated['canonical_path'] ?? $location->content->canonical_path,
            'content_status' => $validated['status'] === 'published' ? 'approved' : 'draft',
            'last_reviewed_at' => $validated['status'] === 'published' ? now() : null,
            'reviewed_by' => $validated['status'] === 'published' ? $request->user()->id : null,
        ]);
        $location->update(['status' => $validated['status']]);
        $this->locationInventory->sync($location->id);

        $this->auditUpdate(
            $request->user()->id,
            'locations.content-update',
            $location->id,
            $previous,
            ['location_status' => $location->status] + $location->content->fresh()->toArray(),
        );

        return redirect()->route('seo.directory.index')->with('status', "Content for {$location->name} updated.");
    }

    public function updateHomepage(UpdateHomepageContentRequest $request): RedirectResponse
    {
        $homepage = PageContent::query()->where('page_key', 'homepage')->firstOrFail();
        $previous = $homepage->only(['heading', 'intro_content', 'bottom_content', 'seo_title', 'meta_description', 'listing_sections']);
        $validated = $request->validated();
        $sections = collect(['vip', 'premium', 'basic', 'new'])
            ->mapWithKeys(fn (string $key) => [$key => $validated['sections'][$key]])
            ->all();

        $homepage->update([
            'heading' => $validated['heading'],
            'intro_content' => $validated['intro_content'],
            'bottom_content' => $validated['bottom_content'] ?? null,
            'seo_title' => $validated['seo_title'],
            'meta_description' => $validated['meta_description'],
            'listing_sections' => $sections,
            'updated_by' => $request->user()->id,
        ]);

        $this->auditUpdate($request->user()->id, 'pages.content-update', $homepage->id, $previous, $homepage->fresh()->toArray());

        return redirect()->route('seo.directory.index')->with('status', 'Homepage content updated.');
    }

    public function updateAgencyDirectory(UpdateAgencyDirectoryContentRequest $request): RedirectResponse
    {
        $content = PageContent::query()->where('page_key', 'agencies')->firstOrFail();
        $previous = $content->only(['heading', 'intro_content', 'bottom_content', 'seo_title', 'meta_description']);
        $content->update($request->validated() + ['updated_by' => $request->user()->id]);

        $this->auditUpdate($request->user()->id, 'pages.content-update', $content->id, $previous, $content->fresh()->toArray());

        return redirect()->route('seo.directory.index')->with('status', 'Agency directory content updated.');
    }

    public function storeTaxonomy(StoreTaxonomyOptionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $slug = Str::slug($validated['label']);

        if (! $slug) {
            return back()->withInput()->withErrors(['label' => 'Enter a label that can produce a stable identifier.']);
        }

        if (TaxonomyOption::query()->where([
            'type' => $validated['type'],
            'slug' => $slug,
            'country_code' => $validated['country_code'],
        ])->exists()) {
            return back()->withInput()->withErrors(['label' => 'That option already exists for this type and country.']);
        }

        $option = TaxonomyOption::query()->create([
            'type' => $validated['type'],
            'slug' => $slug,
            'label' => $validated['label'],
            'country_code' => $validated['country_code'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'settings' => $validated['type'] === 'gender'
                ? ['requires_bust_size' => $validated['requires_bust_size']]
                : null,
        ]);

        $this->audit($request->user()->id, 'taxonomies.create', $option->id, [
            'type' => $option->type,
            'label' => $option->label,
            'country_code' => $option->country_code,
        ], 'Created through directory configuration.');

        return redirect()->route('seo.directory.index')->with('status', "Option {$option->label} created.");
    }

    /** @param  array<string, mixed>  $state */
    private function audit(int $actorId, string $action, int $targetId, array $state, string $reason = 'Created through SEO location management.'): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => str($action)->before('.')->singular()->toString(),
            'target_id' => $targetId,
            'new_state' => $state,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
        ]);
    }

    /** @param array<string, mixed> $previousState
     * @param  array<string, mixed>  $newState
     */
    private function auditUpdate(int $actorId, string $action, int $targetId, array $previousState, array $newState): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => str($action)->before('.')->singular()->toString(),
            'target_id' => $targetId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'reason' => 'Updated through SEO content management.',
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
        ]);
    }
}
