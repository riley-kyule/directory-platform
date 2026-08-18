<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle ?? config('app.name') }}</title>
        <meta name="description" content="{{ $metaDescription ?? 'Browse active provider profiles.' }}">
        <meta name="robots" content="{{ $robots ?? 'index,follow' }}">
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
        @if ($previousUrl)<link rel="prev" href="{{ $previousUrl }}">@endif
        @if ($nextUrl)<link rel="next" href="{{ $nextUrl }}">@endif
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="{{ $socialType }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        @if ($socialImage)
            <meta property="og:image" content="{{ $socialImage }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $socialImage }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="theme-color" content="#171717">
        <meta name="conversion-endpoint" content="{{ route('conversion.contact') }}">
        <x-favicon-link />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {!! \App\Support\JsonLd::script([\App\Support\JsonLd::organization(), \App\Support\JsonLd::website()]) !!}
        {!! $structuredData ?? '' !!}
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <header x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false" class="sticky top-0 z-40 border-b border-white/10 bg-stone-950/95 text-white backdrop-blur">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                @php $logoUrl = app(\App\Services\DirectorySettings::class)->logoUrl(); @endphp
                <a href="{{ route('directory.home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name') }} home">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="h-14 w-auto max-w-[11rem] object-contain">
                    @else
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-rose-500 text-lg font-black">D</span>
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                    @endif
                </a>
                <nav class="hidden items-center gap-2 text-sm font-medium md:flex" aria-label="Primary navigation">
                    <a href="{{ route('directory.search') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Search</a>
                    <a href="{{ route('directory.locations.index') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Locations</a>
                    <a href="{{ route('directory.agencies.index') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Agencies</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-4 py-2 text-stone-950 transition hover:bg-rose-100">Join directory</a>
                    @endauth
                </nav>
                <button type="button" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-public-navigation" class="grid h-11 w-11 place-items-center rounded-full text-white hover:bg-white/10 md:hidden">
                    <span class="sr-only">Open navigation</span>
                    <svg x-show="! mobileOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg x-show="mobileOpen" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <nav id="mobile-public-navigation" x-show="mobileOpen" x-transition x-cloak @click.outside="mobileOpen = false" class="absolute inset-x-0 top-full border-t border-white/10 bg-stone-950 px-4 py-4 shadow-2xl md:hidden" aria-label="Mobile navigation">
                <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold">
                    <a href="{{ route('directory.search') }}" class="rounded-xl px-4 py-3 hover:bg-white/10">Search profiles</a>
                    <a href="{{ route('directory.locations.index') }}" class="rounded-xl px-4 py-3 hover:bg-white/10">Browse locations</a>
                    <a href="{{ route('directory.agencies.index') }}" class="rounded-xl px-4 py-3 hover:bg-white/10">Browse agencies</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="mt-2 rounded-xl bg-white px-4 py-3 text-center text-stone-950">Open dashboard</a>
                    @else
                        <div class="mt-2 grid grid-cols-2 gap-2 border-t border-white/10 pt-4">
                            <a href="{{ route('login') }}" class="rounded-xl border border-white/20 px-4 py-3 text-center">Log in</a>
                            <a href="{{ route('register') }}" class="rounded-xl bg-white px-4 py-3 text-center text-stone-950">Join directory</a>
                        </div>
                    @endauth
                </div>
            </nav>
        </header>

        <main>{{ $slot }}</main>

        <footer class="border-t border-stone-200 bg-white pb-20 md:pb-0">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 text-sm text-stone-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>&copy; {{ now()->year }} {{ config('app.name') }}. Adults only.</p>
                <div class="flex flex-wrap gap-5">
                    <a href="{{ route('directory.locations.index') }}" class="hover:text-stone-900">All locations</a>
                    @foreach ($publishedPolicies as $policy)
                        <a href="{{ $policy->publicRoute() }}" class="hover:text-stone-900">{{ $policy->title }}</a>
                    @endforeach
                    <a href="{{ route('register') }}" class="hover:text-stone-900">Create an account</a>
                    <a href="{{ route('login') }}" class="hover:text-stone-900">Provider login</a>
                </div>
            </div>
        </footer>
    </body>
</html>
