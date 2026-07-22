<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Add location</h2></x-slot>
    <div class="py-12">
        <form method="POST" action="{{ route('seo.locations.store') }}" class="mx-auto grid max-w-4xl gap-5 bg-white p-6 shadow-sm sm:rounded-lg md:grid-cols-2">@csrf
            <div><x-input-label for="name" value="Location name" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
            <div><x-input-label for="country_code" value="Country code" /><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code')" required /><x-input-error :messages="$errors->get('country_code')" class="mt-2" /></div>
            <div><x-input-label for="type" value="Type" /><select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300">@foreach (['country', 'county', 'city', 'town', 'district', 'neighbourhood', 'area'] as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ str($type)->title() }}</option>@endforeach</select></div>
            <div><x-input-label for="parent_id" value="Parent location" /><select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Top level</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->country_code }} · {{ $parent->full_slug }}</option>@endforeach</select></div>
            <div><x-input-label for="status" value="Status" /><select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300"><option value="draft" @selected(old('status') === 'draft')>Draft</option><option value="published" @selected(old('status') === 'published')>Published for onboarding</option></select><p class="mt-1 text-xs text-gray-500">Publishing requires all SEO fields below.</p></div>
            <div class="md:col-span-2"><x-input-label for="intro_content" value="Original introduction" /><textarea id="intro_content" name="intro_content" rows="7" class="mt-1 block w-full rounded-md border-gray-300">{{ old('intro_content') }}</textarea><x-input-error :messages="$errors->get('intro_content')" class="mt-2" /></div>
            <div class="md:col-span-2"><x-input-label for="faq_content" value="FAQ content (optional)" /><textarea id="faq_content" name="faq_content" rows="4" class="mt-1 block w-full rounded-md border-gray-300">{{ old('faq_content') }}</textarea></div>
            <div><x-input-label for="seo_title" value="SEO title" /><x-text-input id="seo_title" name="seo_title" maxlength="70" class="mt-1 block w-full" :value="old('seo_title')" /><x-input-error :messages="$errors->get('seo_title')" class="mt-2" /></div>
            <div><x-input-label for="meta_description" value="Meta description" /><textarea id="meta_description" name="meta_description" maxlength="320" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('meta_description') }}</textarea><x-input-error :messages="$errors->get('meta_description')" class="mt-2" /></div>
            <div class="md:col-span-2"><p class="text-sm text-gray-600">Indexability remains disabled until the location has at least one active profile.</p></div>
            <div class="md:col-span-2 flex justify-end"><x-primary-button>Save location</x-primary-button></div>
        </form>
    </div>
</x-app-layout>
