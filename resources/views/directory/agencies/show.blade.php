<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
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
                    <x-profile-card :profile="$profile" :is-new="$profile->last_activated_at?->gte(now()->subDays(config('directory.new_profile_days')))" />
                @endforeach
            </div>
            @if ($profiles->hasPages())<div class="mt-10">{{ $profiles->onEachSide(1)->links() }}</div>@endif
        </section>
    </div>
</x-public-layout>
