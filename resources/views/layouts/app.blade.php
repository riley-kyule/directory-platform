<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <x-favicon-link />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if (request()->routeIs('admin.policies.edit', 'seo.pages.*.edit', 'seo.locations.create', 'seo.locations.content.edit'))
            @vite('resources/js/admin.js')
        @endif
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <div class="lg:pl-64">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main id="main-content" tabindex="-1">
                    @if ($errors->any())
                        <div class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8" role="alert" aria-live="assertive">
                            <div class="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                                <p class="font-semibold">Please correct the following:</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
