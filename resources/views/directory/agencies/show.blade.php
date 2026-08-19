@php
    $schemas = [
        \App\Support\JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('directory.home')],
            ['name' => 'Agencies', 'url' => route('directory.agencies.index')],
            ['name' => $agency->name, 'url' => route('directory.agencies.show', $agency->slug)],
        ]),
        \App\Support\JsonLd::agency($canonicalUrl, $agency->name, $metaDescription),
    ];
    $profileUrls = $profiles->map(fn ($profile) => route('directory.profiles.show', $profile->slug))->all();
    if ($profileUrls !== []) $schemas[] = \App\Support\JsonLd::itemList($profileUrls);
    $priorityImage = $profiles->first()?->images->first();
    $listingImageSizes = '(min-width: 1280px) 280px, (min-width: 1024px) 30vw, (min-width: 420px) 50vw, 100vw';
@endphp
<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots" :structured-data="\App\Support\JsonLd::script($schemas)" :previous-url="$previousUrl" :next-url="$nextUrl" :preload-image="$priorityImage?->publicUrl('card')" :preload-image-srcset="$priorityImage?->responsiveSrcset(['thumb', 'card', 'profile'])" :preload-image-sizes="$priorityImage ? $listingImageSizes : null">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <nav class="mb-4 text-sm text-stone-500" aria-label="Breadcrumb"><a href="{{ route('directory.home') }}" class="hover:text-stone-950">Home</a><span class="mx-2">/</span><a href="{{ route('directory.agencies.index') }}" class="hover:text-stone-950">Agencies</a><span class="mx-2">/</span><span>{{ $agency->name }}</span></nav>
        <header class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-10">
            <div class="grid h-14 w-14 place-items-center rounded-full bg-rose-100 text-2xl font-black text-rose-600">{{ str($agency->name)->substr(0, 1)->upper() }}</div>
            <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">{{ $agency->name }}</h1>
            @if ($agency->description)<p class="mt-4 max-w-3xl whitespace-pre-line leading-7 text-stone-600">{{ $agency->description }}</p>@endif
        </header>

        <section class="mt-12" aria-labelledby="agency-profiles">
            <div class="mb-6 border-b border-stone-300 pb-4"><h2 id="agency-profiles" class="text-2xl font-black tracking-tight sm:text-3xl">Active profiles</h2><p class="mt-1 text-sm text-stone-500">Profiles currently represented by {{ $agency->name }}.</p></div>
            <div class="grid grid-cols-1 gap-5 min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($profiles as $profile)
                    <x-profile-card :profile="$profile" :is-new="$profile->last_activated_at?->gte(now()->subDays($newProfileDays))" :priority="$loop->first" />
                @endforeach
            </div>
            @if ($totalPages > 1)
                <nav class="mt-10 flex items-center justify-center gap-3 border-t border-stone-200 pt-8" aria-label="Listing pages">
                    @if ($page > 1)<a href="{{ $page === 2 ? route('directory.agencies.show', $agency->slug) : route('directory.agencies.show.page', [$agency->slug, $page - 1]) }}" class="rounded-full border border-stone-300 bg-white px-5 py-2.5 text-sm font-semibold">Previous</a>@endif
                    <span class="text-sm text-stone-500">Page {{ $page }} of {{ $totalPages }}</span>
                    @if ($page < $totalPages)<a href="{{ route('directory.agencies.show.page', [$agency->slug, $page + 1]) }}" class="rounded-full bg-stone-950 px-5 py-2.5 text-sm font-semibold text-white">Next</a>@endif
                </nav>
            @endif
        </section>
    </div>
</x-public-layout>
