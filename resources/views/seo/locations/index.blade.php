<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Locations</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between border-b p-6"><div><h3 class="text-lg font-semibold">Locations</h3><p class="text-sm text-gray-600">Published locations are available during onboarding.</p></div><a href="{{ route('seo.locations.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add location</a></div>
                <div class="divide-y">@forelse ($locations as $location)<div class="flex justify-between gap-4 p-4"><div><p class="font-medium">{{ $location->name }}</p><p class="text-sm text-gray-500">{{ $location->full_slug }} · {{ $location->country_code }}</p></div><div class="text-right"><p class="text-sm capitalize">{{ $location->status }}</p><p class="text-xs text-gray-500">{{ $location->is_indexable ? 'Indexable' : 'Not indexable' }}</p>@if ($location->content)<a href="{{ route('seo.locations.content.edit', $location) }}" class="mt-2 inline-block text-sm font-semibold text-indigo-600">Edit content</a>@endif</div></div>@empty<p class="p-6 text-sm text-gray-600">No locations configured.</p>@endforelse</div>
            </section>
        </div>
    </div>
</x-app-layout>
