<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <header>
            <nav class="mb-4 text-sm text-stone-500" aria-label="Breadcrumb"><a href="{{ route('directory.home') }}" class="hover:text-stone-950">Home</a><span class="mx-2">/</span><span>Agencies</span></nav>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $content->heading }}</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-stone-600">{{ $content->intro_content }}</p>
        </header>

        <section class="mt-10" aria-label="Public agencies">
            @if ($agencies->isNotEmpty())
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($agencies as $agency)
                        <article class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            <div class="grid h-12 w-12 place-items-center rounded-full bg-rose-100 text-xl font-black text-rose-600">{{ str($agency->name)->substr(0, 1)->upper() }}</div>
                            <h2 class="mt-5 text-xl font-black"><a href="{{ route('directory.agencies.show', $agency->slug) }}">{{ $agency->name }}</a></h2>
                            @if ($agency->description)<p class="mt-2 line-clamp-3 text-sm leading-6 text-stone-600">{{ $agency->description }}</p>@endif
                            <div class="mt-5 flex items-center justify-between border-t border-stone-100 pt-4">
                                <span class="text-sm font-semibold text-stone-500">{{ trans_choice(':count active profile|:count active profiles', $agency->public_profiles_count, ['count' => $agency->public_profiles_count]) }}</span>
                                <a href="{{ route('directory.agencies.show', $agency->slug) }}" class="text-sm font-bold text-rose-600">View agency</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                @if ($agencies->hasPages())<div class="mt-10">{{ $agencies->onEachSide(1)->links() }}</div>@endif
            @else
                <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center text-stone-500">No agencies with active profiles are available yet.</div>
            @endif
        </section>

        @if (filled($content->bottom_content))
            <section class="directory-content mt-12 border-t border-stone-200 pt-10" aria-label="Additional agency directory information">
                {!! Str::markdown($content->bottom_content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </section>
        @endif
    </div>
</x-public-layout>
