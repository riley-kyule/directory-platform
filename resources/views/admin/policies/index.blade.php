<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Policy management</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Published policies and drafts</h3>
                    <p class="mt-1 text-sm text-gray-600">Published versions are immutable. Editing starts or resumes a separate draft.</p>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach ($policyTypes as $item)
                        <div class="grid gap-4 p-6 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-center">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $item['label'] }}</p>
                                <p class="text-sm text-gray-500">{{ str($item['type'])->replace('_', ' ')->title() }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Published</p>
                                @if ($item['published'])
                                    <p class="mt-1 text-sm text-gray-800">{{ $item['published']->version }} · {{ $item['published']->published_at->format('j M Y H:i') }}</p>
                                    <a href="{{ $item['published']->publicRoute() }}" target="_blank" class="text-sm font-medium text-indigo-600">View public page</a>
                                @else
                                    <p class="mt-1 text-sm text-gray-500">Not published</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Draft</p>
                                <p class="mt-1 text-sm text-gray-800">{{ $item['draft']?->version ?? 'No draft' }}</p>
                            </div>
                            <a href="{{ route('admin.policies.edit', $item['type']) }}" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                {{ $item['draft'] ? 'Continue editing' : 'Create version' }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
