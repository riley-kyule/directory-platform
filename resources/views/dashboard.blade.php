<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 sm:p-8">
                    <h3 class="text-2xl font-bold">Welcome back, {{ auth()->user()->name }}</h3>
                    @if (auth()->user()->account_type === \App\Enums\AccountType::Provider)
                        <p class="mt-2 max-w-2xl text-gray-700">Continue your provider setup, review profile status, manage media, or submit your listing for staff review.</p>
                        <a href="{{ route('onboarding.index') }}" class="mt-6 inline-flex min-h-12 items-center rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">Open provider workspace</a>
                    @elseif (auth()->user()->hasPermission('profiles.view-private'))
                        <p class="mt-2 max-w-2xl text-gray-700">Review directory listings, pending media, verification, and moderation work.</p>
                        <a href="{{ route('staff.directory.index') }}" class="mt-6 inline-flex min-h-12 items-center rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">Open staff directory</a>
                    @else
                        <p class="mt-2 max-w-2xl text-gray-700">Manage your account details and security settings.</p>
                        <a href="{{ route('profile.edit') }}" class="mt-6 inline-flex min-h-12 items-center rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">Manage account</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
