<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">SEO audit</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6">
                    <h3 class="text-lg font-semibold">Orphan locations</h3>
                    <p class="mt-1 text-sm text-gray-600">Published locations with no active profile — nothing on the site links to these; they're reachable only by direct URL or the sitemap.</p>
                </div>
                <div class="divide-y">
                    @forelse ($orphanLocations as $location)
                        <div class="flex items-center justify-between gap-4 p-4">
                            <div>
                                <p class="font-medium">{{ $location->name }}</p>
                                <p class="text-sm text-gray-500">/{{ $location->full_slug }}-escorts · {{ $location->type }}{{ $location->parent ? ' · under '.$location->parent->name : '' }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $location->is_indexable ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">{{ $location->is_indexable ? 'Indexable' : 'Not indexable' }}</span>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-gray-500">No orphan locations found.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6">
                    <h3 class="text-lg font-semibold">Duplicate SEO titles</h3>
                    <p class="mt-1 text-sm text-gray-600">Different locations sharing the exact same &lt;title&gt; compete with each other in search results instead of each ranking for their own page.</p>
                </div>
                <div class="divide-y">
                    @forelse ($duplicateSeoTitles as $title => $contents)
                        <div class="p-4">
                            <p class="font-medium">&ldquo;{{ $title }}&rdquo;</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($contents as $content)
                                    <li class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">{{ $content->location?->name ?? 'Unknown location' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-gray-500">No duplicate SEO titles found.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6">
                    <h3 class="text-lg font-semibold">Duplicate meta descriptions</h3>
                    <p class="mt-1 text-sm text-gray-600">The same search-result snippet used for more than one page.</p>
                </div>
                <div class="divide-y">
                    @forelse ($duplicateMetaDescriptions as $description => $contents)
                        <div class="p-4">
                            <p class="text-sm text-gray-700">&ldquo;{{ str($description)->limit(140) }}&rdquo;</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($contents as $content)
                                    <li class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">{{ $content->location?->name ?? 'Unknown location' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-gray-500">No duplicate meta descriptions found.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
