<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DirectorySetting;
use App\Services\DirectorySettings;
use App\Services\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SitePresentationController extends Controller
{
    public function edit(DirectorySettings $settings): View
    {
        Gate::authorize('seo.content');

        return view('seo.site-presentation', [
            'profileMetaTemplate' => $settings->string('seo.profile_meta_template'),
            'navigationItems' => $settings->navigationItems(),
        ]);
    }

    public function update(Request $request, PublicPageCache $pageCache): RedirectResponse
    {
        Gate::authorize('seo.content');
        $validated = $request->validate([
            // Any subset of tokens is fine — an omitted one just leaves that
            // detail out and the sentence is tidied up (see ProfileMetaDescription).
            'profile_meta_template' => ['required', 'string', 'max:1000'],
            'navigation_items' => ['required', 'array', 'max:12'],
            'navigation_items.*.label' => ['required', 'string', 'max:40'],
            'navigation_items.*.url' => ['required', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s]*$/'],
        ]);
        $values = [
            'seo.profile_meta_template' => $validated['profile_meta_template'],
            'navigation.primary_items' => json_encode(array_values($validated['navigation_items']), JSON_UNESCAPED_SLASHES),
        ];

        DB::transaction(function () use ($request, $values): void {
            $previous = DirectorySetting::query()->whereIn('key', array_keys($values))->pluck('value', 'key')->all();
            foreach ($values as $key => $value) {
                DirectorySetting::query()->updateOrCreate(['key' => $key], [
                    'value' => $value, 'value_type' => 'string',
                    'group' => str($key)->before('.')->toString(), 'updated_by' => $request->user()->id,
                ]);
            }
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id, 'action' => 'seo.site-presentation-update',
                'target_type' => 'directory-configuration', 'previous_state' => $previous,
                'new_state' => $values, 'reason' => 'Updated profile metadata and public navigation.',
                'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });
        $pageCache->forgetAll();

        return back()->with('status', 'Profile metadata and menu updated.');
    }
}
