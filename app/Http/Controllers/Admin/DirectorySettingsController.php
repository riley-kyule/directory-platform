<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManagePackageDurationRequest;
use App\Http\Requests\UpdateBrandingRequest;
use App\Http\Requests\UpdateDirectorySettingsRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\DirectorySetting;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Services\BrandingImageProcessor;
use App\Services\DirectorySettings;
use App\Services\LocationInventoryService;
use App\Services\PublicPageCache;
use App\Services\SelfDeployService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DirectorySettingsController extends Controller
{
    public function __construct(
        private readonly DirectorySettings $settings,
        private readonly LocationInventoryService $locationInventory,
        private readonly SelfDeployService $selfDeploy,
        private readonly BrandingImageProcessor $brandingImageProcessor,
        private readonly PublicPageCache $pageCache,
    ) {}

    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.index', [
            'logoUrl' => $this->settings->logoUrl(),
            'faviconUrl' => $this->settings->faviconUrl(),
            'settings' => [
                'platform_name' => $this->settings->string('site.platform_name'),
                'support_email' => $this->settings->string('site.support_email'),
                'age_gate_enabled' => $this->settings->boolean('site.age_gate_enabled'),
                'google_site_verification' => $this->settings->string('seo.google_site_verification'),
                'bing_site_verification' => $this->settings->string('seo.bing_site_verification'),
                'privileged_mfa_enforced' => $this->settings->boolean('security.privileged_mfa_enforced'),
                'agency_profile_limit' => $this->settings->integer('profiles.agency_limit'),
                'new_profile_days' => $this->settings->integer('listings.new_profile_days'),
                'listing_rotation_hours' => $this->settings->integer('listings.rotation_hours'),
                'micro_location_min_profiles' => $this->settings->integer('locations.micro_min_profiles'),
                'maximum_file_megabytes' => intdiv($this->settings->integer('media.maximum_file_kilobytes'), 1024),
                'minimum_width' => $this->settings->integer('media.minimum_width'),
                'minimum_height' => $this->settings->integer('media.minimum_height'),
                'maximum_dimension' => $this->settings->integer('media.maximum_dimension'),
                'maximum_megapixels' => intdiv($this->settings->integer('media.maximum_pixels'), 1_000_000),
                'minimum_aspect_ratio' => $this->settings->float('media.minimum_aspect_ratio'),
                'maximum_aspect_ratio' => $this->settings->float('media.maximum_aspect_ratio'),
                'webp_quality' => $this->settings->integer('media.webp_quality'),
                'processing_memory_limit_mb' => $this->settings->integer('media.processing_memory_limit_mb'),
                'video_max_megabytes' => intdiv($this->settings->integer('media.video_max_kilobytes'), 1024),
                'video_max_duration_seconds' => $this->settings->integer('media.video_max_duration_seconds'),
            ],
        ]);
    }

    public function packages(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.packages', [
            'packages' => Package::query()->orderBy('display_order')->get(),
            'durations' => PackageDurationOption::query()->orderBy('display_order')->get(),
        ]);
    }

    public function updates(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.updates', [
            'deployment' => $this->selfDeploy->enabled() ? [
                'current_commit' => $this->selfDeploy->currentCommit(),
                'latest' => Deployment::query()->latest('id')->first(),
            ] : null,
        ]);
    }

    public function update(UpdateDirectorySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $values = [
            'site.platform_name' => [$validated['platform_name'] ?? '', 'string', 'site'],
            'site.support_email' => [$validated['support_email'] ?? '', 'string', 'site'],
            'site.age_gate_enabled' => [$validated['age_gate_enabled'] ? 1 : 0, 'boolean', 'site'],
            'seo.google_site_verification' => [$validated['google_site_verification'] ?? '', 'string', 'seo'],
            'seo.bing_site_verification' => [$validated['bing_site_verification'] ?? '', 'string', 'seo'],
            'security.privileged_mfa_enforced' => [$validated['privileged_mfa_enforced'] ? 1 : 0, 'boolean', 'security'],
            'profiles.agency_limit' => [$validated['agency_profile_limit'], 'integer', 'profiles'],
            'listings.new_profile_days' => [$validated['new_profile_days'], 'integer', 'listings'],
            'listings.rotation_hours' => [$validated['listing_rotation_hours'], 'integer', 'listings'],
            'locations.micro_min_profiles' => [$validated['micro_location_min_profiles'], 'integer', 'locations'],
            'media.maximum_file_kilobytes' => [$validated['maximum_file_megabytes'] * 1024, 'integer', 'media'],
            'media.minimum_width' => [$validated['minimum_width'], 'integer', 'media'],
            'media.minimum_height' => [$validated['minimum_height'], 'integer', 'media'],
            'media.maximum_dimension' => [$validated['maximum_dimension'], 'integer', 'media'],
            'media.maximum_pixels' => [$validated['maximum_megapixels'] * 1_000_000, 'integer', 'media'],
            'media.minimum_aspect_ratio' => [$validated['minimum_aspect_ratio'], 'decimal', 'media'],
            'media.maximum_aspect_ratio' => [$validated['maximum_aspect_ratio'], 'decimal', 'media'],
            'media.webp_quality' => [$validated['webp_quality'], 'integer', 'media'],
            'media.processing_memory_limit_mb' => [$validated['processing_memory_limit_mb'], 'integer', 'media'],
            'media.video_max_kilobytes' => [$validated['video_max_megabytes'] * 1024, 'integer', 'media'],
            'media.video_max_duration_seconds' => [$validated['video_max_duration_seconds'], 'integer', 'media'],
        ];

        DB::transaction(function () use ($request, $values): void {
            $previous = DirectorySetting::query()->whereIn('key', array_keys($values))->pluck('value', 'key')->all();
            foreach ($values as $key => [$value, $type, $group]) {
                DirectorySetting::query()->updateOrCreate(['key' => $key], [
                    'value' => (string) $value,
                    'value_type' => $type,
                    'group' => $group,
                    'updated_by' => $request->user()->id,
                ]);
            }
            $this->audit($request->user()->id, 'settings.update', null, $previous, collect($values)->map(fn ($item) => (string) $item[0])->all());
        });
        Location::query()
            ->whereIn('type', ['area', 'landmark'])
            ->select('id')
            ->eachById(fn (Location $location) => $this->locationInventory->sync($location->id));
        $this->pageCache->forgetAll();

        return back()->with('status', 'Directory settings updated.');
    }

    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $updates = [];

        if ($request->boolean('remove_logo')) {
            $this->deleteBrandingFile('site.logo_path');
            $updates['site.logo_path'] = '';
        } elseif ($request->hasFile('logo')) {
            $updates['site.logo_path'] = $this->storeBrandingFile($request->file('logo'), 'logo', 'site.logo_path');
        }

        if ($request->boolean('remove_favicon')) {
            $this->deleteBrandingFile('site.favicon_path');
            $updates['site.favicon_path'] = '';
        } elseif ($request->hasFile('favicon')) {
            $updates['site.favicon_path'] = $this->storeBrandingFile($request->file('favicon'), 'favicon', 'site.favicon_path');
        }

        if ($updates === []) {
            return back()->withErrors(['logo' => 'Choose a file to upload, or check remove.']);
        }

        DB::transaction(function () use ($request, $updates): void {
            $previous = DirectorySetting::query()->whereIn('key', array_keys($updates))->pluck('value', 'key')->all();
            foreach ($updates as $key => $value) {
                DirectorySetting::query()->updateOrCreate(['key' => $key], [
                    'value' => $value,
                    'value_type' => 'string',
                    'group' => 'site',
                    'updated_by' => $request->user()->id,
                ]);
            }
            $this->audit($request->user()->id, 'settings.branding-update', null, $previous, $updates);
        });
        $this->pageCache->forgetAll();

        return back()->with('status', 'Branding updated.');
    }

    private function storeBrandingFile(UploadedFile $file, string $prefix, string $settingKey): string
    {
        $processed = $this->brandingImageProcessor->process($file, $prefix);
        $this->deleteBrandingFile($settingKey);
        $filename = $prefix.'-'.now()->timestamp.'-'.Str::random(8).'.'.$processed['extension'];
        Storage::disk('branding')->put($filename, $processed['contents']);

        return $filename;
    }

    private function deleteBrandingFile(string $settingKey): void
    {
        $path = $this->settings->string($settingKey);
        if ($path !== '') {
            Storage::disk('branding')->delete($path);
        }
    }

    public function updatePackage(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $validated = $request->validated();
        $updated = DB::transaction(function () use ($request, $package, $validated): bool {
            Package::query()->lockForUpdate()->get();
            $package = Package::query()->findOrFail($package->id);
            if (! $validated['is_active'] && $package->is_active && Package::query()->where('is_active', true)->count() === 1) {
                return false;
            }
            $previous = $package->only(['name', 'image_limit', 'video_limit', 'display_order', 'is_active']);
            $package->update($validated);
            $this->audit($request->user()->id, 'packages.update', $package->id, $previous, $package->fresh()->only(array_keys($previous)));

            return true;
        });
        if (! $updated) {
            return back()->withErrors(['package' => 'At least one package must remain active.']);
        }

        return back()->with('status', "{$validated['name']} package updated.");
    }

    public function storeDuration(ManagePackageDurationRequest $request): RedirectResponse
    {
        $duration = PackageDurationOption::query()->create($request->validated());
        $this->audit($request->user()->id, 'package-durations.create', $duration->id, [], $duration->toArray());

        return back()->with('status', "{$duration->label} duration added.");
    }

    public function updateDuration(ManagePackageDurationRequest $request, PackageDurationOption $duration): RedirectResponse
    {
        $validated = $request->validated();
        $updated = DB::transaction(function () use ($request, $duration, $validated): bool {
            PackageDurationOption::query()->lockForUpdate()->get();
            $duration = PackageDurationOption::query()->findOrFail($duration->id);
            if (! $validated['is_active'] && $duration->is_active
                && PackageDurationOption::query()->where('is_active', true)->count() === 1) {
                return false;
            }
            $previous = $duration->only(['label', 'duration_days', 'display_order', 'is_active']);
            $duration->update($validated);
            $this->audit($request->user()->id, 'package-durations.update', $duration->id, $previous, $duration->fresh()->only(array_keys($previous)));

            return true;
        });
        if (! $updated) {
            return back()->withErrors(['duration' => 'At least one package duration must remain active.']);
        }

        return back()->with('status', "{$validated['label']} duration updated.");
    }

    /** @param array<string, mixed> $previous
     * @param  array<string, mixed>  $new
     */
    private function audit(int $actorId, string $action, ?int $targetId, array $previous, array $new): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => 'directory-configuration',
            'target_id' => $targetId,
            'previous_state' => $previous,
            'new_state' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
        ]);
    }
}
