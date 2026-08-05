@php
    $breadcrumbItems = [
        ['name' => 'Home', 'url' => route('directory.home')],
        ['name' => 'All locations', 'url' => $canonicalUrl],
    ];
@endphp
<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots" :structured-data="\App\Support\JsonLd::script([\App\Support\JsonLd::breadcrumbs($breadcrumbItems)])">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
        <header>
            <nav class="mb-4 text-sm text-stone-500" aria-label="Breadcrumb"><a href="{{ route('directory.home') }}" class="hover:text-stone-950">Home</a><span class="mx-2">/</span><span>All locations</span></nav>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">All locations</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-stone-600">Every city, neighbourhood, and area we cover.</p>
        </header>

        <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-5 text-sm">
            @if ($locationTree->isNotEmpty())
                <ul class="space-y-1">
                    @foreach ($locationTree as $node)
                        @include('directory.partials.location-sidebar-node', ['node' => $node])
                    @endforeach
                </ul>
            @else
                <p class="text-stone-500">No published locations yet.</p>
            @endif
        </div>
    </div>
</x-public-layout>
