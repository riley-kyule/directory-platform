<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Admin settings</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">The change could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Directory operation</h3>
                    <p class="mt-1 text-sm text-gray-600">These values take effect without a deployment. The server upload ceiling remains 50 MB.</p>
                </div>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    @method('PATCH')
                    <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:col-span-2 lg:col-span-3">
                        <input type="checkbox" name="privileged_mfa_enforced" value="1" @checked(old('privileged_mfa_enforced', $settings['privileged_mfa_enforced'])) class="mt-1 rounded border-gray-300 text-indigo-600">
                        <span>
                            <strong class="block text-sm text-gray-900">Require authenticator MFA for privileged roles</strong>
                            <span class="mt-1 block text-sm text-gray-600">Optional. When enabled, Admin, CSR, and SEO accounts must enroll and complete an authenticator challenge. Leave disabled when privileged authentication is handled through an approved SSO provider.</span>
                        </span>
                    </label>
                    @foreach ([
                        ['agency_profile_limit', 'Agency profile limit', 1, 100, 1],
                        ['new_profile_days', 'New profile window (days)', 1, 365, 1],
                        ['listing_rotation_hours', 'Listing rotation interval (hours)', 1, 168, 1],
                        ['micro_location_min_profiles', 'Micro-location index threshold', 2, 100, 1],
                        ['maximum_file_megabytes', 'Maximum image size (MB)', 1, 50, 1],
                        ['minimum_width', 'Minimum image width (px)', 200, 5000, 1],
                        ['minimum_height', 'Minimum image height (px)', 200, 5000, 1],
                        ['maximum_dimension', 'Maximum image dimension (px)', 600, 20000, 1],
                        ['maximum_megapixels', 'Maximum decoded megapixels', 1, 100, 1],
                        ['minimum_aspect_ratio', 'Minimum aspect ratio', 0.1, 5, 0.1],
                        ['maximum_aspect_ratio', 'Maximum aspect ratio', 0.1, 5, 0.1],
                        ['webp_quality', 'WebP quality', 50, 100, 1],
                    ] as [$field, $label, $min, $max, $step])
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" type="number" :min="$min" :max="$max" :step="$step" class="mt-1 block w-full" :value="old($field, $settings[$field])" required />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                    <div class="flex items-end lg:col-span-3"><x-primary-button>Save operational settings</x-primary-button></div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Packages</h3>
                <p class="mt-1 text-sm text-gray-600">Package codes and listing sections remain fixed; display names, image limits, order, and availability are editable.</p>
                <div class="mt-6 space-y-4">
                    @foreach ($packages as $package)
                        <form method="POST" action="{{ route('admin.settings.packages.update', $package) }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                            @csrf
                            @method('PATCH')
                            <div><x-input-label value="Code" /><p class="mt-2 font-mono text-sm uppercase text-gray-700">{{ $package->code }}</p></div>
                            <div><x-input-label :for="'package_name_'.$package->id" value="Display name" /><x-text-input :id="'package_name_'.$package->id" name="name" class="mt-1 block w-full" :value="$package->name" required /></div>
                            <div><x-input-label :for="'image_limit_'.$package->id" value="Image limit" /><x-text-input :id="'image_limit_'.$package->id" name="image_limit" type="number" min="1" max="50" class="mt-1 block w-full" :value="$package->image_limit" required /></div>
                            <div><x-input-label :for="'package_order_'.$package->id" value="Display order" /><x-text-input :id="'package_order_'.$package->id" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" :value="$package->display_order" required /></div>
                            <div class="flex items-center justify-between gap-4 lg:block"><label class="text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($package->is_active)> Active</label><x-primary-button class="lg:mt-3">Save package</x-primary-button></div>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Package durations</h3>
                <div class="mt-6 space-y-4">
                    @foreach ($durations as $duration)
                        <form method="POST" action="{{ route('admin.settings.durations.update', $duration) }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                            @csrf
                            @method('PATCH')
                            <div><x-input-label :for="'duration_label_'.$duration->id" value="Label" /><x-text-input :id="'duration_label_'.$duration->id" name="label" class="mt-1 block w-full" :value="$duration->label" required /></div>
                            <div><x-input-label :for="'duration_days_'.$duration->id" value="Days" /><x-text-input :id="'duration_days_'.$duration->id" name="duration_days" type="number" min="1" max="3650" class="mt-1 block w-full" :value="$duration->duration_days" required /></div>
                            <div><x-input-label :for="'duration_order_'.$duration->id" value="Display order" /><x-text-input :id="'duration_order_'.$duration->id" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" :value="$duration->display_order" required /></div>
                            <div class="flex items-center justify-between gap-4 lg:block"><label class="text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($duration->is_active)> Active</label><x-primary-button class="lg:mt-3">Save duration</x-primary-button></div>
                        </form>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('admin.settings.durations.store') }}" class="mt-8 grid gap-4 rounded-lg border-2 border-dashed border-gray-300 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    @csrf
                    <div><x-input-label for="new_duration_label" value="New duration label" /><x-text-input id="new_duration_label" name="label" class="mt-1 block w-full" placeholder="45 days" required /></div>
                    <div><x-input-label for="new_duration_days" value="Days" /><x-text-input id="new_duration_days" name="duration_days" type="number" min="1" max="3650" class="mt-1 block w-full" required /></div>
                    <div><x-input-label for="new_duration_order" value="Display order" /><x-text-input id="new_duration_order" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" value="100" required /></div>
                    <div><input type="hidden" name="is_active" value="1"><x-primary-button>Add duration</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
