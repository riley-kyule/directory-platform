<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle ?? config('app.name') }}</title>
        <meta name="description" content="{{ $metaDescription ?? 'Browse active provider profiles.' }}">
        <meta name="robots" content="{{ $robots ?? 'index,follow' }}">
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
        <meta name="theme-color" content="#171717">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-stone-950/95 text-white backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('directory.home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name') }} home">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-rose-500 text-lg font-black">D</span>
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>
                <nav class="flex items-center gap-2 text-sm font-medium">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-full px-4 py-2 hover:bg-white/10">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 hover:bg-white/10 sm:inline-flex">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-4 py-2 text-stone-950 transition hover:bg-rose-100">Join directory</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main>{{ $slot }}</main>

        <footer class="border-t border-stone-200 bg-white pb-20 md:pb-0">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 text-sm text-stone-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>&copy; {{ now()->year }} {{ config('app.name') }}. Adults only.</p>
                <div class="flex flex-wrap gap-5">
                    <a href="{{ route('register') }}" class="hover:text-stone-900">Create an account</a>
                    <a href="{{ route('login') }}" class="hover:text-stone-900">Provider login</a>
                </div>
            </div>
        </footer>
    </body>
</html>
