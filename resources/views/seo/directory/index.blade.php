<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Directory configuration</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div><h3 class="text-lg font-semibold">Homepage content</h3><p class="text-sm text-gray-600">Controls public homepage copy and listing-section labels. Bottom content supports Markdown and appears immediately before the footer.</p></div>
                <form method="POST" action="{{ route('seo.pages.homepage.update') }}" class="mt-6 grid gap-5 md:grid-cols-2">@csrf @method('PATCH')
                    <div class="md:col-span-2"><x-input-label for="homepage_heading" value="Page heading" /><x-text-input id="homepage_heading" name="heading" class="mt-1 block w-full" :value="old('heading', $homepage->heading)" required /><x-input-error :messages="$errors->get('heading')" class="mt-2" /></div>
                    <div class="md:col-span-2"><x-input-label for="homepage_intro" value="Introduction" /><textarea id="homepage_intro" name="intro_content" rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('intro_content', $homepage->intro_content) }}</textarea></div>
                    @foreach (['vip', 'premium', 'basic', 'new'] as $section)
                        <fieldset class="rounded-md border p-4"><legend class="px-1 text-sm font-semibold uppercase">{{ $section }} section</legend>
                            <div><x-input-label :for="$section.'_heading'" value="Heading" /><x-text-input :id="$section.'_heading'" :name="'sections['.$section.'][heading]'" class="mt-1 block w-full" :value="old('sections.'.$section.'.heading', $homepage->listing_sections[$section]['heading'])" required /></div>
                            <div class="mt-3"><x-input-label :for="$section.'_description'" value="Description" /><textarea id="{{ $section }}_description" name="sections[{{ $section }}][description]" rows="2" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('sections.'.$section.'.description', $homepage->listing_sections[$section]['description']) }}</textarea></div>
                        </fieldset>
                    @endforeach
                    <div class="md:col-span-2"><x-input-label for="homepage_bottom_content" value="Bottom SEO content (Markdown supported)" /><textarea id="homepage_bottom_content" name="bottom_content" rows="10" class="mt-1 block w-full rounded-md border-gray-300">{{ old('bottom_content', $homepage->bottom_content) }}</textarea><p class="mt-1 text-xs text-gray-500">Use ## for headings, - for lists, and [text](URL) for links.</p></div>
                    <div><x-input-label for="homepage_seo_title" value="SEO title" /><x-text-input id="homepage_seo_title" name="seo_title" maxlength="70" class="mt-1 block w-full" :value="old('seo_title', $homepage->seo_title)" required /></div>
                    <div><x-input-label for="homepage_meta_description" value="Meta description" /><textarea id="homepage_meta_description" name="meta_description" maxlength="320" rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('meta_description', $homepage->meta_description) }}</textarea></div>
                    <div class="md:col-span-2 flex justify-end"><x-primary-button>Save homepage content</x-primary-button></div>
                </form>
            </section>
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between border-b p-6"><div><h3 class="text-lg font-semibold">Locations</h3><p class="text-sm text-gray-600">Published locations are available during onboarding.</p></div><a href="{{ route('seo.locations.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add location</a></div>
                    <div class="divide-y">@forelse ($locations as $location)<div class="flex justify-between gap-4 p-4"><div><p class="font-medium">{{ $location->name }}</p><p class="text-sm text-gray-500">{{ $location->full_slug }} · {{ $location->country_code }}</p></div><div class="text-right"><p class="text-sm capitalize">{{ $location->status }}</p><p class="text-xs text-gray-500">{{ $location->is_indexable ? 'Indexable' : 'Not indexable' }}</p>@if ($location->status === 'published')<a href="{{ route('seo.locations.content.edit', $location) }}" class="mt-2 inline-block text-sm font-semibold text-indigo-600">Edit content</a>@endif</div></div>@empty<p class="p-6 text-sm text-gray-600">No locations configured.</p>@endforelse</div>
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
