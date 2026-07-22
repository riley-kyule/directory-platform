<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Directory configuration</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between border-b p-6"><div><h3 class="text-lg font-semibold">Locations</h3><p class="text-sm text-gray-600">Published locations are available during onboarding.</p></div><a href="{{ route('seo.locations.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add location</a></div>
                    <div class="divide-y">@forelse ($locations as $location)<div class="flex justify-between p-4"><div><p class="font-medium">{{ $location->name }}</p><p class="text-sm text-gray-500">{{ $location->full_slug }} · {{ $location->country_code }}</p></div><div class="text-right"><p class="text-sm capitalize">{{ $location->status }}</p><p class="text-xs text-gray-500">{{ $location->is_indexable ? 'Indexable' : 'Not indexable' }}</p></div></div>@empty<p class="p-6 text-sm text-gray-600">No locations configured.</p>@endforelse</div>
                </section>
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold">Add taxonomy option</h3>
                    <form method="POST" action="{{ route('seo.taxonomies.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                        <div><x-input-label for="type" value="Type" /><select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300">@foreach (['gender', 'ethnicity', 'hair_color', 'hair_length', 'bust_size', 'build', 'sexual_orientation', 'language', 'service', 'rate_period'] as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                        <div><x-input-label for="label" value="Label" /><x-text-input id="label" name="label" class="mt-1 block w-full" :value="old('label')" required /><x-input-error :messages="$errors->get('label')" class="mt-2" /></div>
                        <div><x-input-label for="country_code" value="Country code (optional)" /><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code')" /></div>
                        <div><x-input-label for="sort_order" value="Sort order" /><x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', 100)" required /></div>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="requires_bust_size" value="1" @checked(old('requires_bust_size'))> Require bust size for this gender</label>
                        <div class="sm:col-span-2"><x-primary-button>Add option</x-primary-button></div>
                    </form>
                    <div class="mt-8 max-h-96 divide-y overflow-y-auto border-t">@foreach ($taxonomyOptions as $option)<div class="flex justify-between py-3 text-sm"><span>{{ $option->label }}</span><span class="text-gray-500">{{ str($option->type)->replace('_', ' ') }}{{ $option->country_code ? ' · '.$option->country_code : '' }}</span></div>@endforeach</div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
