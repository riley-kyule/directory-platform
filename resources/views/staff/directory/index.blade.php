<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Manage listings</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">New profiles are intentionally repeated in their package section and the New section. Private listings never appear in public HTML.</div>

            @foreach ([
                'vip' => ['VIP Escorts', 'Active VIP package profiles.'],
                'premium' => ['Premium Escorts', 'Active Premium package profiles.'],
                'basic' => ['Basic Escorts', 'Active Basic package profiles.'],
                'new' => ['New Escorts', 'Profiles activated during the configured New window.'],
                'private' => ['Private Escorts', 'Draft, pending, expired, rejected, deactivated, banned, or package-ineligible profiles.'],
            ] as $key => [$heading, $description])
                @php($profiles = $sections[$key])
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg" aria-labelledby="staff-section-{{ $key }}">
                    <div class="flex items-end justify-between gap-4 border-b border-gray-200 p-5 sm:p-6">
                        <div><h3 id="staff-section-{{ $key }}" class="text-lg font-bold text-gray-900">{{ $heading }}</h3><p class="mt-1 text-sm text-gray-600">{{ $description }}</p></div>
                        <span class="text-sm font-semibold text-gray-500">{{ number_format($profiles->total()) }}</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse ($profiles as $profile)
                            <x-staff-profile-row :profile="$profile" />
                        @empty
                            <p class="p-8 text-center text-sm text-gray-500">No profiles in this section.</p>
                        @endforelse
                    </div>
                    @if ($profiles->hasPages())<div class="border-t border-gray-100 p-4">{{ $profiles->withQueryString()->links() }}</div>@endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
