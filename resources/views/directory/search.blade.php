<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-wider text-rose-600">Directory search</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
                @if (filled($filters['q'] ?? null))
                    Results for “{{ $filters['q'] }}”
                @else
                    Find a profile
                @endif
            </h1>
            <p class="mt-3 text-stone-600">Filter active listings by their approved public profile details.</p>
        </header>

        <x-search-filters
            :filters="$filters"
            :search-cities="$searchCities"
            :search-neighbourhoods="$searchNeighbourhoods"
            :search-taxonomies="$searchTaxonomies"
            class="mt-8"
        />

        <section class="mt-12" aria-labelledby="search-results">
            <div class="mb-6 flex items-end justify-between border-b border-stone-300 pb-4">
                <h2 id="search-results" class="text-2xl font-black">Active profiles</h2>
                <p class="text-sm font-semibold text-stone-500">{{ $profiles->total() }} {{ Str::plural('result', $profiles->total()) }}</p>
            </div>

            @if ($profiles->isNotEmpty())
                <div class="grid grid-cols-1 gap-5 min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($profiles as $profile)
                        <x-profile-card :profile="$profile" />
                    @endforeach
                </div>
                @if ($profiles->hasPages())
                    <div class="mt-10">{{ $profiles->links() }}</div>
                @endif
            @else
                <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-14 text-center">
                    <h2 class="text-xl font-black">No matching active profiles</h2>
                    <p class="mt-2 text-stone-500">Try removing one or more filters.</p>
                </div>
            @endif
        </section>
    </div>
</x-public-layout>
