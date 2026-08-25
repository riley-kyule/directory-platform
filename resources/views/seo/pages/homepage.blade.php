<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Homepage Content</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div><h3 class="text-lg font-semibold">Homepage content</h3><p class="text-sm text-gray-600">Controls public homepage copy and listing-section labels. Bottom content supports Markdown and appears immediately before the footer.</p></div>
                <form method="POST" action="{{ route('seo.pages.homepage.update') }}" class="mt-6 grid gap-5 md:grid-cols-2">@csrf @method('PATCH')
                    <div class="md:col-span-2"><x-input-label for="homepage_heading" value="Page heading" /><x-text-input id="homepage_heading" name="heading" class="mt-1 block w-full" :value="old('heading', $homepage->heading)" required /><x-input-error :messages="$errors->get('heading')" class="mt-2" /></div>
                    <div class="md:col-span-2"><x-input-label for="homepage_intro" value="Introduction" /><textarea id="homepage_intro" name="intro_content" data-html-editor rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('intro_content', $homepage->intro_content) }}</textarea></div>
                    @foreach (['vip', 'premium', 'basic', 'new'] as $section)
                        <fieldset class="rounded-md border p-4"><legend class="px-1 text-sm font-semibold uppercase">{{ $section }} section</legend>
                            <div><x-input-label :for="$section.'_heading'" value="Heading" /><x-text-input :id="$section.'_heading'" :name="'sections['.$section.'][heading]'" class="mt-1 block w-full" :value="old('sections.'.$section.'.heading', $homepage->listing_sections[$section]['heading'])" required /></div>
                            <div class="mt-3"><x-input-label :for="$section.'_description'" value="Description" /><textarea id="{{ $section }}_description" name="sections[{{ $section }}][description]" rows="2" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('sections.'.$section.'.description', $homepage->listing_sections[$section]['description']) }}</textarea></div>
                        </fieldset>
                    @endforeach
                    <div class="md:col-span-2"><x-input-label for="homepage_bottom_content" value="Bottom SEO content" /><textarea id="homepage_bottom_content" name="bottom_content" data-html-editor rows="10" class="mt-1 block w-full rounded-md border-gray-300">{{ old('bottom_content', $homepage->bottom_content) }}</textarea></div>
                    <div><x-input-label for="homepage_seo_title" value="SEO title" /><x-text-input id="homepage_seo_title" name="seo_title" maxlength="70" class="mt-1 block w-full" :value="old('seo_title', $homepage->seo_title)" required /></div>
                    <div><x-input-label for="homepage_meta_description" value="Meta description" /><textarea id="homepage_meta_description" name="meta_description" maxlength="160" rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('meta_description', $homepage->meta_description) }}</textarea></div>
                    <div class="md:col-span-2 flex justify-end"><x-primary-button>Save homepage content</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
