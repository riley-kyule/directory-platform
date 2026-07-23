<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <div class="mx-auto max-w-7xl space-y-16 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <header>
            @if ($location?->parent)
                <nav class="mb-4 text-sm text-stone-500" aria-label="Breadcrumb">
                    <a href="{{ route('directory.home') }}" class="hover:text-stone-950">Home</a>
                    <span class="mx-2">/</span>
                    @if ($location->parent->parent)
                        <a href="{{ route('directory.cities.show', $location->parent->parent->slug) }}" class="hover:text-stone-950">{{ $location->parent->parent->name }}</a>
                        <span class="mx-2">/</span>
                        <a href="{{ route('directory.neighbourhoods.show', [$location->parent->parent->slug, $location->parent->slug]) }}" class="hover:text-stone-950">{{ $location->parent->name }}</a>
                    @else
                        <a href="{{ route('directory.cities.show', $location->parent->slug) }}" class="hover:text-stone-950">{{ $location->parent->name }}</a>
                    @endif
                    <span class="mx-2">/</span><span>{{ $location->name }}</span>
                </nav>
            @endif
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $heading }}</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-stone-600">{{ $intro }}</p>
        </header>

        @foreach (['vip', 'premium', 'basic', 'new'] as $key)
            @php
                $title = $sectionContent[$key]['heading'];
                $description = $sectionContent[$key]['description'];
            @endphp
            <section aria-labelledby="section-{{ $key }}">
                <div class="mb-6 flex items-end justify-between gap-6 border-b border-stone-300 pb-4">
                    <div>
                        <h2 id="section-{{ $key }}" class="text-2xl font-black tracking-tight sm:text-3xl">{{ $title }}</h2>
                        <p class="mt-1 text-sm text-stone-500">{{ $description }}</p>
                    </div>
                    <span class="hidden text-sm font-semibold text-stone-400 sm:block">{{ $sections[$key]->count() }} shown</span>
                </div>
                @if ($sections[$key]->isNotEmpty())
                    <div class="grid grid-cols-1 gap-5 min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($sections[$key] as $profile)
                            <x-profile-card :profile="$profile" :is-new="$key === 'new' || $profile->last_activated_at?->gte(now()->subDays($newProfileDays))" />
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center text-stone-500">
                        No active {{ strtolower($title) }} are available here yet.
                    </div>
                @endif
            </section>
        @endforeach

        @if ($totalPages > 1)
            <nav class="flex items-center justify-center gap-3 border-t border-stone-200 pt-8" aria-label="Listing pages">
                @if ($page > 1)<a href="{{ $page === 2 ? Str::before($canonicalUrl, '/page/'.$page) : Str::before($canonicalUrl, '/page/'.$page).'/page/'.($page - 1) }}" class="rounded-full border border-stone-300 bg-white px-5 py-2.5 text-sm font-semibold">Previous</a>@endif
                <span class="text-sm text-stone-500">Page {{ $page }} of {{ $totalPages }}</span>
                @if ($page < $totalPages)<a href="{{ ($page === 1 ? $canonicalUrl : Str::before($canonicalUrl, '/page/'.$page)).'/page/'.($page + 1) }}" class="rounded-full bg-stone-950 px-5 py-2.5 text-sm font-semibold text-white">Next</a>@endif
            </nav>
        @endif

        @if (filled($bottomContent))
            <section class="directory-content border-t border-stone-200 pt-10" aria-label="Additional directory information">
                {!! Str::markdown($bottomContent, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </section>
        @endif
    </div>
</x-public-layout>
