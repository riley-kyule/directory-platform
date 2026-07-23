<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Provider onboarding') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @error('media')<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror

            @if ($user->provider_type === \App\Enums\ProviderType::Independent)
                <section class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Independent profile') }}</h3>
                    @if ($user->profile)
                        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div><dt class="text-sm text-gray-500">Name</dt><dd class="font-medium">{{ $user->profile->display_name }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Status</dt><dd class="font-medium capitalize">{{ str($user->profile->status->value)->replace('_', ' ') }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Requested package</dt><dd class="font-medium">{{ $user->profile->packageRequests->last()?->requestedPackage?->name ?? '—' }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-4">
                            <a href="{{ route('provider.profiles.show', $user->profile) }}" class="inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">View profile</a>
                            <a href="{{ route('profiles.media.index', $user->profile) }}" class="inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">{{ in_array($user->profile->status, [\App\Enums\ProfileStatus::Draft, \App\Enums\ProfileStatus::Active], true) ? 'Manage media' : 'View media' }}</a>
                        </div>
                        @if ($user->profile->status === \App\Enums\ProfileStatus::Draft)
                            <form method="POST" action="{{ route('onboarding.profiles.submit', $user->profile) }}" class="mt-5">
                                @csrf
                                <x-primary-button>Submit for review</x-primary-button>
                            </form>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-600">Complete your listing and choose a package. Staff will review it before publication.</p>
                        <a href="{{ route('onboarding.profiles.create') }}" class="mt-5 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Create profile</a>
                    @endif
                </section>
            @else
                @if (! $user->agency)
                    <section class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Register your agency') }}</h3>
                        <form method="POST" action="{{ route('onboarding.agency.store') }}" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="name" value="Agency name" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="description" value="Agency description" />
                                <textarea id="description" name="description" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                            <x-primary-button>Save agency</x-primary-button>
                        </form>
                    </section>
                @else
                    <section class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $user->agency->name }}</h3>
                                <p class="text-sm text-gray-600">{{ $user->agency->profiles->count() }} of {{ config('directory.agency_profile_limit') }} profile slots used</p>
                            </div>
                            @if ($user->agency->profiles->count() < config('directory.agency_profile_limit'))
                                <a href="{{ route('onboarding.profiles.create') }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add profile</a>
                            @endif
                        </div>
                        <div class="mt-6 divide-y divide-gray-200">
                            @forelse ($user->agency->profiles as $profile)
                                <div class="flex items-center justify-between py-4">
                                    <div><p class="font-medium">{{ $profile->display_name }}</p><p class="text-sm capitalize text-gray-500">{{ str($profile->status->value)->replace('_', ' ') }}</p></div>
                                    <div class="text-right">
                                        <span class="text-sm text-gray-600">{{ $profile->packageRequests->last()?->requestedPackage?->name ?? 'No package' }}</span>
                                        <a href="{{ route('provider.profiles.show', $profile) }}" class="mt-2 block text-sm font-medium text-indigo-600">View profile</a>
                                        <a href="{{ route('profiles.media.index', $profile) }}" class="mt-2 block text-sm font-medium text-indigo-600">{{ in_array($profile->status, [\App\Enums\ProfileStatus::Draft, \App\Enums\ProfileStatus::Active], true) ? 'Manage media' : 'View media' }}</a>
                                        @if ($profile->status === \App\Enums\ProfileStatus::Draft)
                                            <form method="POST" action="{{ route('onboarding.profiles.submit', $profile) }}" class="mt-2">
                                                @csrf
                                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Submit for review</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="py-6 text-sm text-gray-600">No profiles have been added yet.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
