<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Search insights</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">Search terms that were used more than 10 times on a single day. Lower-volume terms are never recorded here, so this is popularity data only — no individual searches or searchers are identifiable.</p>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
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
            </section>
        </div>
    </div>
</x-app-layout>
