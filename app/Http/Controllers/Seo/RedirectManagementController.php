<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDirectoryRedirectRequest;
use App\Http\Requests\UpdateProfileSlugRequest;
use App\Models\AuditLog;
use App\Models\DirectoryRedirect;
use App\Models\Profile;
use App\Models\ProfileSlugHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RedirectManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('seo.redirects');

        return view('seo.redirects.index', [
            'redirects' => DirectoryRedirect::query()->with('creator')->latest()->paginate(30),
            'profiles' => Profile::query()
                ->select(['id', 'display_name', 'slug', 'status'])
                ->orderBy('display_name')
                ->limit(500)
                ->get(),
            'slugHistory' => ProfileSlugHistory::query()
                ->with(['profile:id,display_name,slug', 'changedBy:id,name,email'])
                ->latest('changed_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(StoreDirectoryRedirectRequest $request): RedirectResponse
    {
        $redirect = DirectoryRedirect::query()->create($request->validated() + [
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $this->audit($request, 'redirects.create', $redirect, null, $redirect->toArray());

        return back()->with('status', 'Redirect rule created.');
    }

    public function toggle(Request $request, DirectoryRedirect $redirect): RedirectResponse
    {
        Gate::authorize('seo.redirects');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $previous = $redirect->only(['is_active']);
        $redirect->update(['is_active' => ! $redirect->is_active]);
        $this->audit($request, 'redirects.toggle', $redirect, $previous, $redirect->only(['is_active']), $validated['reason']);

        return back()->with('status', $redirect->is_active ? 'Redirect activated.' : 'Redirect deactivated.');
    }

    public function updateProfileSlug(UpdateProfileSlugRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $profile, $validated): void {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            $oldSlug = $profile->slug;
            $newSlug = $validated['slug'];
            $oldPath = '/escort/'.$oldSlug;
            $newPath = '/escort/'.$newSlug;

            ProfileSlugHistory::query()->create([
                'profile_id' => $profile->id,
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
            DirectoryRedirect::query()
                ->where('target_path', $oldPath)
                ->update(['target_path' => $newPath]);
            $redirect = DirectoryRedirect::query()->updateOrCreate(
                ['source_path' => $oldPath],
                [
                    'target_path' => $newPath,
                    'status_code' => 301,
                    'reason' => $validated['reason'],
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ],
            );
            $profile->update(['slug' => $newSlug]);

            $this->audit(
                $request,
                'profiles.slug-change',
                $profile,
                ['slug' => $oldSlug],
                ['slug' => $newSlug, 'redirect_id' => $redirect->id],
                $validated['reason'],
            );
        });

        return back()->with('status', 'Profile slug changed and the old URL now redirects permanently.');
    }

    /** @param array<string, mixed>|null $previous
     * @param  array<string, mixed>  $new
     */
    private function audit(
        Request $request,
        string $action,
        object $target,
        ?array $previous,
        array $new,
        ?string $reason = null,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => $target instanceof Profile ? 'profile' : 'redirect',
            'target_id' => $target->id,
            'previous_state' => $previous,
            'new_state' => $new,
            'reason' => $reason ?? $target->reason,
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);
    }
}
