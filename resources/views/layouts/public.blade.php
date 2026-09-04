<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle ?? config('app.name') }}</title>
        <meta name="description" content="{{ $metaDescription ?? 'Browse active provider profiles.' }}">
        <meta name="robots" content="{{ $robots ?? 'index,follow' }}">
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
        @php
            $directorySettings = app(\App\Services\DirectorySettings::class);
            $googleVerification = $directorySettings->string('seo.google_site_verification');
            $bingVerification = $directorySettings->string('seo.bing_site_verification');
            $primaryNavigation = $directorySettings->navigationItems();
        @endphp
        @if ($googleVerification !== '')<meta name="google-site-verification" content="{{ $googleVerification }}">@endif
        @if ($bingVerification !== '')<meta name="msvalidate.01" content="{{ $bingVerification }}">@endif
        @if ($previousUrl)<link rel="prev" href="{{ $previousUrl }}">@endif
        @if ($nextUrl)<link rel="next" href="{{ $nextUrl }}">@endif
        @if ($preloadImage)<link rel="preload" as="image" href="{{ $preloadImage }}" @if($preloadImageSrcset) imagesrcset="{{ $preloadImageSrcset }}" @endif @if($preloadImageSizes) imagesizes="{{ $preloadImageSizes }}" @endif fetchpriority="high">@endif
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
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ route('pwa.icon', 180) }}">
        <meta name="conversion-endpoint" content="{{ route('conversion.contact') }}">
        @if ($profileViewId)
            <meta name="profile-view-endpoint" content="{{ route('conversion.profile-view') }}">
            <meta name="profile-view-id" content="{{ $profileViewId }}">
        @endif
        <x-favicon-link />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {!! \App\Support\JsonLd::script([\App\Support\JsonLd::organization(), \App\Support\JsonLd::website()]) !!}
        {!! $structuredData ?? '' !!}
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        @php $ageGateRequired = request()->attributes->get('age_gate_required', false); @endphp
        <a href="#main-content" class="fixed left-4 top-4 z-[60] -translate-y-24 rounded-lg bg-white px-4 py-3 font-bold text-stone-950 shadow-xl transition focus:translate-y-0">Skip to main content</a>
        <header @if($ageGateRequired) inert aria-hidden="true" @endif x-data="{
            mobileOpen: false,
            toggleMenu() {
                this.mobileOpen ? this.closeMenu() : (this.mobileOpen = true, this.$nextTick(() => this.$refs.mobileMenuFirst.focus()));
            },
            closeMenu(restoreFocus = true) {
                this.mobileOpen = false;
                if (restoreFocus) this.$nextTick(() => this.$refs.mobileMenuButton.focus());
            },
        }" @keydown.escape.window="if (mobileOpen) closeMenu()" class="sticky top-0 z-40 border-b border-white/10 bg-stone-950/95 text-white backdrop-blur">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                @php $logoUrl = app(\App\Services\DirectorySettings::class)->logoUrl(); @endphp
                <a href="{{ route('directory.home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name') }} home">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" width="600" height="180" decoding="async" class="h-14 w-auto max-w-[11rem] object-contain">
                    @else
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-rose-500 text-lg font-black">D</span>
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                    @endif
                </a>
                <nav class="hidden items-center gap-2 text-sm font-medium md:flex" aria-label="Primary navigation">
                    @foreach ($primaryNavigation as $item)
                        <a href="{{ url($item['url']) }}" class="rounded-full px-4 py-2 hover:bg-white/10">{{ $item['label'] }}</a>
                    @endforeach
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-4 py-2 text-stone-950 transition hover:bg-rose-100">Join directory</a>
                    @endauth
                </nav>
                <button x-ref="mobileMenuButton" type="button" @click="toggleMenu()" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-public-navigation" class="grid h-11 w-11 place-items-center rounded-full text-white hover:bg-white/10 md:hidden">
                    <span class="sr-only" x-text="mobileOpen ? 'Close navigation' : 'Open navigation'">Open navigation</span>
                    <svg x-show="! mobileOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg x-show="mobileOpen" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <nav id="mobile-public-navigation" x-show="mobileOpen" x-transition x-cloak @click.outside="if (mobileOpen) closeMenu()" class="absolute inset-x-0 top-full border-t border-white/10 bg-stone-950 px-4 py-4 shadow-2xl md:hidden" aria-label="Mobile navigation">
                <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold">
                    @foreach ($primaryNavigation as $item)
                        <a @if($loop->first) x-ref="mobileMenuFirst" @endif href="{{ url($item['url']) }}" class="rounded-xl px-4 py-3 hover:bg-white/10">{{ $item['label'] }}</a>
                    @endforeach
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

        <main id="main-content" tabindex="-1"@if($ageGateRequired) inert aria-hidden="true"@endif>{{ $slot }}</main>

        <footer @if($ageGateRequired) inert aria-hidden="true" @endif class="border-t border-stone-200 bg-white pb-20 md:pb-0">
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
        @if ($ageGateRequired)
            <div class="fixed inset-0 z-[100] grid min-h-screen place-items-center overflow-y-auto bg-stone-950/95 p-4" role="dialog" aria-modal="true" aria-labelledby="age-gate-title" aria-describedby="age-gate-description">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 text-center shadow-2xl sm:p-8">
                    <h1 id="age-gate-title" class="text-3xl font-black text-stone-950">Adults only</h1>
                    <p id="age-gate-description" class="mt-3 leading-7 text-stone-700">You must be at least 18 years old, or the age of majority where you live, to enter this directory.</p>
                    <form method="POST" action="{{ route('age-gate.confirm') }}" class="mt-6">@csrf
                        <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                        <button autofocus class="min-h-12 w-full rounded-xl bg-rose-700 px-6 py-3 font-bold text-white hover:bg-rose-800">I am 18 or older</button>
                    </form>
                    <p class="mt-4 text-xs text-stone-600">By continuing, you agree to follow the laws that apply where you live.</p>
                </div>
            </div>
        @endif
    </body>
</html>
