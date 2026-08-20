<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Moderation metrics</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('staff.moderation.index') }}" class="text-sm font-semibold text-indigo-600">&larr; Back to moderation queue</a>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Open urgent reports</h3>
                    <p class="mt-2 text-4xl font-black text-red-600">{{ $metrics['open_urgent_reports'] }}</p>
                    <p class="mt-1 text-sm text-gray-500">Urgent-priority reports still new or in review right now.</p>
                </section>
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Average resolution time</h3>
                    <p class="mt-2 text-4xl font-black text-gray-900">{{ $metrics['average_resolution_hours'] !== null ? number_format($metrics['average_resolution_hours'], 1).'h' : '—' }}</p>
                    <p class="mt-1 text-sm text-gray-500">From submission to closure for cases resolved in the last 90 days.</p>
                </section>
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Overdue reports</h3>
                    <p class="mt-2 text-4xl font-black {{ $metrics['overdue_urgent_reports'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $metrics['overdue_urgent_reports'] + $metrics['overdue_normal_reports'] }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $metrics['overdue_urgent_reports'] }} urgent · {{ $metrics['overdue_normal_reports'] }} normal</p>
                </section>
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Queue ownership</h3>
                    <p class="mt-2 text-4xl font-black text-gray-900">{{ $metrics['unassigned_open_reports'] }}</p>
                    <p class="mt-1 text-sm text-gray-500">Unassigned open reports · oldest {{ $metrics['oldest_open_hours'] !== null ? number_format($metrics['oldest_open_hours'], 1).'h' : '—' }}</p>
                </section>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold text-gray-900">Reports by status</h3></div>
                    <dl class="divide-y">
                        @forelse ($metrics['reports_by_status'] as $status => $total)
                            <div class="flex items-center justify-between px-5 py-3 text-sm"><dt class="capitalize text-gray-600">{{ str($status)->replace('_', ' ') }}</dt><dd class="font-semibold text-gray-900">{{ $total }}</dd></div>
                        @empty
                            <p class="px-5 py-6 text-sm text-gray-500">No reports yet.</p>
                        @endforelse
                    </dl>
                </section>
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold text-gray-900">Reports by category</h3></div>
                    <dl class="divide-y">
                        @forelse ($metrics['reports_by_category'] as $category => $total)
                            <div class="flex items-center justify-between px-5 py-3 text-sm"><dt class="text-gray-600">{{ $categoryLabels[$category] ?? $category }}</dt><dd class="font-semibold text-gray-900">{{ $total }}</dd></div>
                        @empty
                            <p class="px-5 py-6 text-sm text-gray-500">No reports yet.</p>
                        @endforelse
                    </dl>
                </section>
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold text-gray-900">Reports by priority</h3></div>
                    <dl class="divide-y">
                        @forelse ($metrics['reports_by_priority'] as $priority => $total)
                            <div class="flex items-center justify-between px-5 py-3 text-sm"><dt class="capitalize text-gray-600">{{ $priority }}</dt><dd class="font-semibold text-gray-900">{{ $total }}</dd></div>
                        @empty
                            <p class="px-5 py-6 text-sm text-gray-500">No reports yet.</p>
                        @endforelse
                    </dl>
                </section>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold text-gray-900">Actions taken, last 30 days</h3></div>
                    <dl class="divide-y">
                        @forelse ($metrics['actions_last_30_days'] as $action => $total)
                            <div class="flex items-center justify-between px-5 py-3 text-sm"><dt class="capitalize text-gray-600">{{ str($action)->replace('_', ' ') }}</dt><dd class="font-semibold text-gray-900">{{ $total }}</dd></div>
                        @empty
                            <p class="px-5 py-6 text-sm text-gray-500">No actions in the last 30 days.</p>
                        @endforelse
                    </dl>
                </section>
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold text-gray-900">Appeals by status</h3></div>
                    <dl class="divide-y">
                        @forelse ($metrics['appeals_by_status'] as $status => $total)
                            <div class="flex items-center justify-between px-5 py-3 text-sm"><dt class="capitalize text-gray-600">{{ $status }}</dt><dd class="font-semibold text-gray-900">{{ $total }}</dd></div>
                        @empty
                            <p class="px-5 py-6 text-sm text-gray-500">No appeals yet.</p>
                        @endforelse
                    </dl>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
