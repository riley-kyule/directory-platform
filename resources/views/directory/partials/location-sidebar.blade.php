@if ($activeLocations->isNotEmpty())
    <div x-data="{ open: window.innerWidth >= 1024 }"
         @resize.window.debounce="if (window.innerWidth >= 1024) open = true">
        <button
            type="button"
            @click="open = ! open"
            :aria-expanded="open.toString()"
            aria-controls="location-sidebar-nav"
            class="flex w-full items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white p-4 text-left text-sm font-semibold text-stone-900"
        >
            <span>Browse locations</span>
            <svg class="h-4 w-4 shrink-0 text-stone-400 transition-transform" :class="{ 'rotate-90': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
            </svg>
        </button>

        <nav
            id="location-sidebar-nav"
            aria-label="Browse by location"
            x-show="open"
            x-cloak
            x-transition.opacity
            class="mt-2 rounded-2xl border border-stone-200 bg-white p-5 text-sm"
        >
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-stone-500">Locations</h2>
            <ul class="space-y-1">
                @foreach ($activeLocations as $node)
                    @include('directory.partials.location-sidebar-node', ['node' => $node])
                @endforeach
            </ul>
            <a href="{{ route('directory.locations.index') }}" class="mt-4 block text-xs font-semibold text-rose-600 hover:text-rose-700">View all locations &rarr;</a>
        </nav>
    </div>
@endif
