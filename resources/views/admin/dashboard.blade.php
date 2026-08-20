<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if($metrics['moderation_overdue'] > 0)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 p-5" role="alert">
                    <div><p class="font-bold text-red-950">{{ $metrics['moderation_overdue'] }} overdue moderation {{ Str::plural('case', $metrics['moderation_overdue']) }}</p><p class="mt-1 text-sm text-red-800">Response targets have been exceeded and staff action is required.</p></div>
                    <a href="{{ route('staff.moderation.index', ['sla' => 'overdue']) }}" class="inline-flex min-h-11 items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Review overdue cases</a>
                </div>
            @endif
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Active listings</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $metrics['profiles_active'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        @foreach ($metrics['profiles_by_status'] as $status => $total)
                            <span class="mr-2">{{ str($status)->replace('_', ' ')->title() }}: {{ $total }}</span>
                        @endforeach
                    </p>
                </div>
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Pages</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $metrics['pages_count'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $metrics['locations_published'] }} published locations</p>
                </div>
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Users</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $metrics['users_total'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        @foreach ($metrics['users_by_role'] as $roleName => $total)
                            <span class="mr-2">{{ $roleName }}: {{ $total }}</span>
                        @endforeach
                    </p>
                </div>
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm font-medium text-gray-500">Searches (7 days)</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $metrics['search_total_last_7_days'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">Across {{ $metrics['search_top_terms']->count() }} top terms below</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Recent activity</h3>
                    <div class="mt-4 divide-y">
                        @forelse ($metrics['recent_activity'] as $entry)
                            <div class="py-2 text-sm">
                                <p class="text-gray-900">{{ str($entry->action)->replace(['.', '-', '_'], ' ')->title() }}</p>
                                <p class="text-xs text-gray-500">{{ $entry->actor?->name ?? 'System' }} &middot; {{ $entry->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-gray-600">No activity recorded yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Top search terms (7 days)</h3>
                    <div class="mt-4 divide-y">
                        @forelse ($metrics['search_top_terms'] as $term)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-gray-900">{{ $term->term }}</span>
                                <span class="text-gray-500">{{ $term->search_count }}</span>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-gray-600">No search activity logged in the last 7 days.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
