<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">SEO audit</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-gray-950 text-white shadow-sm sm:rounded-lg">
                <div class="grid gap-6 p-6 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-300">Organic search readiness</p>
                        <h3 class="mt-2 text-2xl font-bold">{{ $auditIssueCount === 0 ? 'No detected SEO issues' : $auditIssueCount.' '.Str::plural('check', $auditIssueCount).' need attention' }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-300">This audit checks crawl paths, indexability, metadata uniqueness and length, content depth, and editorial freshness. Fix critical indexing conflicts first, then thin or stale content.</p>
                    </div>
                    <div class="grid min-w-56 grid-cols-2 gap-2 text-center">
                        <div class="rounded-xl bg-white/10 p-3"><p class="text-2xl font-black">{{ $locationQualityIssues->where('severity', 'critical')->count() }}</p><p class="text-xs text-gray-300">Critical pages</p></div>
                        <div class="rounded-xl bg-white/10 p-3"><p class="text-2xl font-black">{{ $orphanLocations->count() }}</p><p class="text-xs text-gray-300">Orphans</p></div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6">
                    <h3 class="text-lg font-semibold">Location page quality</h3>
                    <p class="mt-1 text-sm text-gray-600">Published pages with conflicting indexability, weak metadata, thin copy, or content that has not received a recent editorial review.</p>
                </div>
                <div class="divide-y">
                    @forelse ($locationQualityIssues as $result)
                        <div class="p-4 sm:flex sm:items-start sm:justify-between sm:gap-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium">{{ $result['location']->name }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $result['severity'] === 'critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($result['severity']) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">/{{ $result['location']->full_slug }}-escorts · {{ $result['location']->active_profile_count }} active {{ Str::plural('profile', $result['location']->active_profile_count) }}</p>
                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                                    @foreach ($result['issues'] as $issue)<li>{{ $issue }}</li>@endforeach
                                </ul>
                            </div>
                            @if ($result['location']->content)
                                <a href="{{ route('seo.locations.content.edit', $result['location']) }}" class="mt-4 inline-flex min-h-11 shrink-0 items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:mt-0">Fix page</a>
                            @endif
                        </div>
                    @empty
                        <p class="p-6 text-sm text-gray-500">All published location pages meet the current quality checks.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6">
                    <h3 class="text-lg font-semibold">Core landing page quality</h3>
                    <p class="mt-1 text-sm text-gray-600">Metadata and content-depth checks for the homepage and agency directory.</p>
                </div>
                <div class="divide-y">
                    @forelse ($pageQualityIssues as $result)
                        <div class="p-4 sm:flex sm:items-start sm:justify-between sm:gap-6">
                            <div>
                                <p class="font-medium">{{ $result['label'] }}</p>
                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                                    @foreach ($result['issues'] as $issue)<li>{{ $issue }}</li>@endforeach
                                </ul>
                            </div>
                            <a href="{{ $result['page']->page_key === 'homepage' ? route('seo.pages.homepage.edit') : route('seo.pages.agencies.edit') }}" class="mt-4 inline-flex min-h-11 shrink-0 items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:mt-0">Fix page</a>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-gray-500">Core landing pages meet the current quality checks.</p>
                    @endforelse
                </div>
            </section>

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
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $location->is_indexable ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' }}">{{ $location->is_indexable ? 'Indexing conflict' : 'Not indexable' }}</span>
                                @if ($location->content)<a href="{{ route('seo.locations.content.edit', $location) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Review</a>@endif
                            </div>
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
