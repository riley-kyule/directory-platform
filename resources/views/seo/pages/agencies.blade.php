<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Agency Page Content</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div><h3 class="text-lg font-semibold">Agency directory content</h3><p class="text-sm text-gray-600">Controls the copy and metadata on the public agencies index.</p></div>
                <form method="POST" action="{{ route('seo.pages.agencies.update') }}" class="mt-6 grid gap-5 md:grid-cols-2">@csrf @method('PATCH')
                    <div class="md:col-span-2"><x-input-label for="agencies_heading" value="Page heading" /><x-text-input id="agencies_heading" name="heading" class="mt-1 block w-full" :value="old('heading', $agencyDirectory->heading)" required /></div>
                    <div class="md:col-span-2"><x-input-label for="agencies_intro" value="Introduction" /><textarea id="agencies_intro" name="intro_content" data-markdown-editor rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('intro_content', $agencyDirectory->intro_content) }}</textarea></div>
                    <div class="md:col-span-2"><x-input-label for="agencies_bottom_content" value="Bottom SEO content" /><textarea id="agencies_bottom_content" name="bottom_content" data-markdown-editor rows="8" class="mt-1 block w-full rounded-md border-gray-300">{{ old('bottom_content', $agencyDirectory->bottom_content) }}</textarea></div>
                    <div><x-input-label for="agencies_seo_title" value="SEO title" /><x-text-input id="agencies_seo_title" name="seo_title" maxlength="70" class="mt-1 block w-full" :value="old('seo_title', $agencyDirectory->seo_title)" required /></div>
                    <div><x-input-label for="agencies_meta_description" value="Meta description" /><textarea id="agencies_meta_description" name="meta_description" maxlength="160" rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('meta_description', $agencyDirectory->meta_description) }}</textarea></div>
                    <div class="md:col-span-2 flex justify-end"><x-primary-button>Save agency directory content</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
