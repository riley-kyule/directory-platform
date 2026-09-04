<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Directory structure</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Locations</h2></div>
            <a href="{{ route('seo.locations.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700"><span class="text-lg leading-none">+</span> Add location</a>
        </div>
    </x-slot>
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" role="status"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">✓</span>{{ session('status') }}</div>@endif

            <section class="admin-card">
                <div class="admin-card-header bg-gradient-to-r from-gray-50 via-white to-indigo-50">
                    <form method="GET" action="{{ route('seo.locations.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <label for="location-search" class="relative min-w-0 flex-1">
                            <span class="sr-only">Search locations</span>
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2" stroke-linecap="round"/></svg>
                            <input id="location-search" name="q" type="search" value="{{ $search }}" placeholder="Search by location name, URL path or country code…" class="admin-field w-full py-3 pl-12 pr-4">
                        </label>
                        <button class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-gray-700">Search</button>
                        @if ($search !== '')<a href="{{ route('seo.locations.index') }}" class="rounded-xl border border-gray-200 bg-white px-5 py-3 text-center text-sm font-bold text-gray-600 transition hover:bg-gray-50">Clear</a>@endif
                    </form>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                        <span class="rounded-full bg-white px-3 py-1.5 font-semibold shadow-sm ring-1 ring-gray-200">{{ number_format($locations->total()) }} {{ Str::plural('location', $locations->total()) }}</span>
                        @if ($search !== '')<span>matching “<strong class="text-gray-800">{{ $search }}</strong>”</span>@else<span>Published locations are available during provider onboarding.</span>@endif
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse ($locations as $location)
                        <article class="group grid gap-4 px-5 py-5 transition hover:bg-indigo-50/40 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $location->status === 'published' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21s7-5.25 7-12a7 7 0 10-14 0c0 6.75 7 12 7 12z"/><circle cx="12" cy="9" r="2.5" stroke-width="2"/></svg>
                                </span>
                                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate font-bold text-gray-900">{{ $location->name }}</h3><span class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ $location->type }}</span>@if($location->is_indexable)<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Indexable</span>@endif</div><p class="mt-1 truncate font-mono text-xs text-gray-400">/{{ $location->full_slug }} · {{ $location->country_code }}</p>@if($location->parent)<p class="mt-1 text-xs text-gray-500">Inside {{ $location->parent->name }}</p>@endif</div>
                            </div>
                            <div class="flex items-center justify-between gap-4 sm:justify-end">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold {{ $location->status === 'published' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' }}"><span class="h-1.5 w-1.5 rounded-full {{ $location->status === 'published' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>{{ str($location->status)->title() }}</span>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('seo.locations.edit', $location) }}" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700">Details</a>
                                    @if ($location->content)<a href="{{ route('seo.locations.content.edit', $location) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition group-hover:border-indigo-200 hover:!border-indigo-300 hover:text-indigo-700">Content <span aria-hidden="true">→</span></a>@endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gray-100 text-gray-400"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2" stroke-linecap="round"/></svg></span><h3 class="mt-4 font-bold text-gray-800">{{ $search !== '' ? 'No matching locations' : 'No locations yet' }}</h3><p class="mt-1 text-sm text-gray-500">{{ $search !== '' ? 'Try another name, path, or country code.' : 'Add your first location to start building the directory.' }}</p></div>
                    @endforelse
                </div>
                @if ($locations->hasPages())<div class="border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:px-6">{{ $locations->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
