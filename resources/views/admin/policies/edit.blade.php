<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $label }}</h2>
            <a href="{{ route('admin.policies.index') }}" class="text-sm font-semibold text-indigo-600">Back to policies</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.policies.save', $policyType) }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')

                @if ($published)
                    <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        Current public version: <strong>{{ $published->version }}</strong>, published {{ $published->published_at->format('j M Y H:i') }}.
                        This form creates a new version and never changes the published copy.
                    </div>
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="version" value="Version identifier" />
                        <x-text-input id="version" name="version" class="mt-1 block w-full" :value="old('version', $draft?->version)" placeholder="2026-07" required />
                        <x-input-error :messages="$errors->get('version')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="title" value="Public title" />
                        <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $draft?->title ?? $label)" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="summary" value="Meta description / summary" />
                    <textarea id="summary" name="summary" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('summary', $draft?->summary) }}</textarea>
                    <x-input-error :messages="$errors->get('summary')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="content" value="Policy content (Markdown supported)" />
                    <textarea id="content" name="content" rows="22" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('content', $draft?->content) }}</textarea>
                    <x-input-error :messages="$errors->get('content')" class="mt-2" />
                </div>

                <label class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <input type="checkbox" name="requires_reacceptance" value="1" @checked(old('requires_reacceptance', $draft?->requires_reacceptance ?? false)) class="mt-0.5 rounded border-gray-300 text-indigo-600">
                    <span><strong>Material change:</strong> require users who accepted an earlier version of this policy to accept this version again.</span>
                </label>

                <div class="flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-5">
                    <button type="submit" name="action" value="save_draft" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Save draft</button>
                    <button type="submit" name="action" value="publish" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" onclick="return confirm('Publish this version? Published policy versions cannot be edited.')">Publish version</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
