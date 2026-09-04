<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Media for {{ $profile->display_name }}</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif

            <div class="flex justify-end">
                <a href="{{ route('onboarding.index') }}" class="text-sm font-medium text-indigo-600">Back to onboarding</a>
            </div>

            <x-media-manager
                :profile="$profile"
                :can-manage="$canManage"
                :photo-limit="$limit"
                :video-limit="$videoLimit" />

            @unless ($canManage)
                <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">Media cannot be changed while this profile is in its current state.</p>
            @endunless
        </div>
    </div>
</x-app-layout>
