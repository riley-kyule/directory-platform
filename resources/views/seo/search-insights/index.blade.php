<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Search and conversion insights</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="search-engine-setup">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 id="search-engine-setup" class="text-lg font-semibold text-gray-900">Search-engine setup</h3>
                        <p class="mt-1 text-sm text-gray-600">Verify ownership, then submit this sitemap index in each webmaster dashboard: <code class="break-all">{{ $sitemapUrl }}</code></p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full px-3 py-1 font-semibold {{ $verification['google'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">Google {{ $verification['google'] ? 'tag ready' : 'tag missing' }}</span>
                        <span class="rounded-full px-3 py-1 font-semibold {{ $verification['bing'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">Bing {{ $verification['bing'] ? 'tag ready' : 'tag optional' }}</span>
                        @can('settings.manage')<a href="{{ route('admin.settings.index') }}#google_site_verification" class="rounded-full bg-gray-900 px-3 py-1 font-semibold text-white">Configure tags</a>@endcan
                    </div>
                </div>
            </section>

            <section aria-labelledby="contact-insights">
                <div>
                    <h3 id="contact-insights" class="text-lg font-semibold text-gray-900">Contact intent — last 30 days</h3>
                    <p class="mt-1 text-sm text-gray-600">Aggregated clicks on public contact actions. No visitor, session, IP address, device, or user-agent information is stored. Counts are directional analytics, not billing records.</p>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Profile views</p><p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($totalProfileViews) }}</p></div>
                    <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Contact clicks</p><p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($totalContactClicks) }}</p><p class="mt-1 text-xs text-gray-500">{{ $overallClickThroughRate === null ? 'No view data yet' : number_format($overallClickThroughRate, 1).'% CTR' }}</p></div>
                    @foreach (['call' => 'Calls', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS'] as $channel => $label)
                        <div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($channelTotals->get($channel, 0)) }}</p></div>
                    @endforeach
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 px-5 py-4"><h4 class="font-semibold text-gray-900">Profile performance</h4><p class="mt-1 text-xs text-gray-500">Ranked by views, then clicks; CTR is contact clicks divided by profile views.</p></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Profile</th><th class="px-5 py-3 text-right">Views</th><th class="px-5 py-3 text-right">Clicks</th><th class="px-5 py-3 text-right">CTR</th></tr></thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($profilePerformance as $performance)
                                        <tr><td class="px-5 py-4 font-semibold">{{ $performance['profile']?->display_name ?? 'Deleted profile' }}</td><td class="px-5 py-4 text-right">{{ number_format($performance['views']) }}</td><td class="px-5 py-4 text-right">{{ number_format($performance['clicks']) }}</td><td class="px-5 py-4 text-right">{{ number_format($performance['ctr'] ?? 0, 1) }}%</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">No profile views recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 px-5 py-4"><h4 class="font-semibold text-gray-900">Contact placement</h4></div>
                        <div class="divide-y divide-gray-200">
                            @forelse ($placementTotals as $placement => $total)
                                <div class="flex items-center justify-between px-5 py-4 text-sm"><span class="font-medium text-gray-700">{{ str($placement)->replace('_', ' ')->headline() }}</span><strong>{{ number_format($total) }}</strong></div>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">No placement data recorded yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-gray-200 pt-8" aria-labelledby="popular-searches">
                <h3 id="popular-searches" class="text-lg font-semibold text-gray-900">Popular searches</h3>
                <p class="mt-1 text-sm text-gray-600">Search terms that were used more than 10 times on a single day. Lower-volume terms are never recorded here, so this is popularity data only — no individual searches or searchers are identifiable.</p>

                <div class="mt-5 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Term</th><th class="px-5 py-3">Searches that day</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($terms as $term)
                                    <tr>
                                        <td class="px-5 py-4 text-gray-600">{{ date('j M Y', strtotime($term->search_date)) }}</td>
                                        <td class="px-5 py-4 font-semibold">{{ $term->term }}</td>
                                        <td class="px-5 py-4">{{ $term->search_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No search term has crossed the daily threshold yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($terms->hasPages())<div class="border-t p-5">{{ $terms->links() }}</div>@endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
