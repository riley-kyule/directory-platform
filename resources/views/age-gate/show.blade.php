<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Age verification — {{ config('app.name') }}</title>
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="#171717">
        @vite(['resources/css/app.css'])
    </head>
    <body class="grid min-h-screen place-items-center bg-stone-950 px-4 font-sans text-white antialiased">
        <div class="w-full max-w-md rounded-3xl border border-white/10 bg-stone-900 p-8 text-center shadow-2xl">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-rose-500 text-xl font-black">18+</span>
            <h1 class="mt-5 text-2xl font-black tracking-tight">You must be 18 or older</h1>
            <p class="mt-3 text-sm leading-6 text-stone-400">{{ config('app.name') }} contains adult-oriented listings intended only for visitors who are at least 18 years old (or the age of majority in your jurisdiction, if higher). By continuing, you confirm you meet this requirement.</p>

            <form method="POST" action="{{ route('age-gate.confirm') }}" class="mt-8 space-y-3">
                @csrf
                <input type="hidden" name="redirect" value="{{ $intendedUrl }}">
                <button type="submit" class="w-full rounded-full bg-rose-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-400">I am 18 or older — Enter</button>
            </form>
            <a href="https://www.google.com" class="mt-3 block text-sm font-semibold text-stone-400 hover:text-stone-200">I am under 18 — Take me elsewhere</a>
        </div>
    </body>
</html>
