<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\StoreTaxonomyOptionRequest;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\TaxonomyOption;
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
    ];

    public function index(): View
    {
        Gate::authorize('seo.locations');

        return view('seo.directory.index', [
            'locations' => Location::query()->with('parent')->orderBy('country_code')->orderBy('full_slug')->get(),
            'taxonomyOptions' => TaxonomyOption::query()->orderBy('type')->orderBy('sort_order')->orderBy('label')->get(),
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

            if ($validated['status'] === 'published') {
                DB::table('location_contents')->insert([
                    'location_id' => $location->id,
                    'intro_content' => $validated['intro_content'],
                    'faq_content' => ! empty($validated['faq_content']) ? json_encode(['content' => $validated['faq_content']]) : null,
                    'seo_title' => $validated['seo_title'],
                    'meta_description' => $validated['meta_description'],
                    'canonical_path' => $parent ? '/'.$parent->slug.'/'.$slug.'-escorts' : '/'.$slug.'-escorts',
                    'content_status' => 'approved',
                    'last_reviewed_at' => now(),
                    'reviewed_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->audit($request->user()->id, 'locations.create', $location->id, [
                'name' => $location->name,
                'status' => $location->status,
                'full_slug' => $location->full_slug,
            ]);

            return $location;
        });

        return redirect()->route('seo.directory.index')->with('status', "Location {$location->name} created.");
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
}
