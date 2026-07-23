<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $profile->display_name }}</h2>
            <a href="{{ route('onboarding.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Back to provider dashboard</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-gray-500">Profile status</p>
                        <p class="mt-1 text-lg font-semibold capitalize text-gray-900">{{ str($profile->status->value)->replace('_', ' ') }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $profile->currentPackageAssignment?->package?->name ?? $profile->packageRequests->last()?->requestedPackage?->name ?? 'No package selected' }}
                            @if ($profile->expires_at) · {{ $profile->expires_at->isPast() ? 'Expired' : 'Expires' }} {{ $profile->expires_at->format('j M Y') }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($canEdit)
                            <a href="{{ route('provider.profiles.edit', $profile) }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Edit profile</a>
                        @endif
                        <a href="{{ route('profiles.media.index', $profile) }}" class="inline-flex rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ $canEdit ? 'Manage media' : 'View media' }}
                        </a>
                    </div>
                </div>

                @if (! $canEdit && in_array($profile->status, [\App\Enums\ProfileStatus::Expired, \App\Enums\ProfileStatus::Deactivated], true))
                    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        This profile is private and cannot be edited. Request renewal below to return it to staff review.
                    </div>
                @elseif ($profile->status === \App\Enums\ProfileStatus::PendingReview)
                    <div class="mt-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        This profile is awaiting staff review. Editing and media changes are temporarily locked.
                    </div>
                @endif
            </section>

            <section class="grid gap-6 bg-white p-6 shadow-sm sm:rounded-lg md:grid-cols-2">
                <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-900">Profile information</h3></div>
                <div><p class="text-sm text-gray-500">Location</p><p class="font-medium">{{ $profile->primaryLocation->name }} / {{ $profile->sublocation->name }}@if($profile->microLocation) / {{ $profile->microLocation->name }}@endif</p></div>
                <div><p class="text-sm text-gray-500">Gender</p><p class="font-medium">{{ $profile->gender->label }}</p></div>
                <div><p class="text-sm text-gray-500">Ethnicity</p><p class="font-medium">{{ $profile->ethnicity->label }}</p></div>
                <div><p class="text-sm text-gray-500">Build</p><p class="font-medium">{{ $profile->build->label }}</p></div>
                <div class="md:col-span-2"><p class="text-sm text-gray-500">About</p><p class="mt-1 whitespace-pre-line text-gray-800">{{ $profile->description }}</p></div>
                <div class="md:col-span-2"><p class="text-sm text-gray-500">Services</p><p class="font-medium">{{ $profile->services->pluck('label')->join(', ') }}</p></div>
                <div class="md:col-span-2"><p class="text-sm text-gray-500">Contact methods</p><p class="font-medium">{{ $profile->contacts->map(fn ($contact) => str($contact->type)->replace('_', ' ')->title().': '.$contact->display_value)->join(' · ') }}</p></div>
            </section>

            @if ($canRenew)
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Request renewal</h3>
                    <p class="mt-1 text-sm text-gray-600">Choose the package you want. Staff will select the duration and reactivate the profile after review.</p>
                    <form method="POST" action="{{ route('provider.profiles.renewal.store', $profile) }}" class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="requested_package_id" value="Requested package" />
                            <select id="requested_package_id" name="requested_package_id" required class="mt-1 block w-full rounded-md border-gray-300">
                                <option value="">Choose package</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('requested_package_id') == $package->id)>{{ $package->name }} · up to {{ $package->image_limit }} images</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('requested_package_id')" class="mt-2" />
                        </div>
                        <x-policy-acceptances :policies="$renewalPolicies" class="sm:basis-full" />
                        <x-primary-button>Request renewal</x-primary-button>
                    </form>
                </section>
            @elseif ($profile->packageRequests->where('status', \App\Enums\PackageRequestStatus::Pending)->isNotEmpty())
                <section class="rounded-md border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                    A package request is awaiting staff review. You will be able to manage this profile again after activation.
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
