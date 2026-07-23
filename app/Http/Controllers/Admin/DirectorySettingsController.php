<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManagePackageDurationRequest;
use App\Http\Requests\UpdateDirectorySettingsRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\AuditLog;
use App\Models\DirectorySetting;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Services\DirectorySettings;
use App\Services\LocationInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DirectorySettingsController extends Controller
{
    public function __construct(
        private readonly DirectorySettings $settings,
        private readonly LocationInventoryService $locationInventory,
    ) {}

    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.index', [
            'settings' => [
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
            ],
            'packages' => Package::query()->orderBy('display_order')->get(),
            'durations' => PackageDurationOption::query()->orderBy('display_order')->get(),
        ]);
    }

    public function update(UpdateDirectorySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $values = [
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

        return back()->with('status', 'Directory settings updated.');
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
            $previous = $package->only(['name', 'image_limit', 'display_order', 'is_active']);
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
