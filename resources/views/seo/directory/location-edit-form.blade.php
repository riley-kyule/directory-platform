<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Edit {{ $location->name }}</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->has('location'))
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first('location') }}</div>
            @endif

            <form method="POST" action="{{ route('seo.locations.update', $location) }}" class="grid gap-5 bg-white p-6 shadow-sm sm:rounded-lg">@csrf @method('PATCH')
                <p class="font-mono text-xs text-gray-400">/{{ $location->full_slug }} · {{ str($location->type)->title() }}@if ($location->parent) · inside {{ $location->parent->name }}@endif</p>

                <div>
                    <x-input-label for="name" value="Display name" />
                    <x-text-input id="name" name="name" maxlength="160" class="mt-1 block w-full" :value="old('name', $location->name)" required />
                    <p class="mt-1 text-xs text-gray-500">The heading and menu label. The URL path stays <span class="font-mono">/{{ $location->full_slug }}</span>.</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="country_code" value="Country code" />
                    @if ($location->parent_id === null)
                        <x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code', $location->country_code)" required />
                        <p class="mt-1 text-xs text-gray-500">ISO two-letter code (e.g. <strong>AE</strong> for United Arab Emirates). Sets the country name and nationality shown in profile SEO text. Sub-locations inherit it.</p>
                    @else
                        <x-text-input id="country_code" class="mt-1 block w-full bg-gray-100 uppercase" :value="$location->country_code" readonly disabled />
                        <p class="mt-1 text-xs text-gray-500">Inherited from {{ $location->parent->name }}. Edit it on the top-level location.</p>
                    @endif
                    <x-input-error :messages="$errors->get('country_code')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('seo.locations.index') }}" class="text-sm text-gray-600">Cancel</a>
                    <x-primary-button>Save changes</x-primary-button>
                </div>
            </form>

            <div class="rounded-lg border border-red-200 bg-red-50 p-6">
                <h3 class="font-bold text-red-900">Delete this location</h3>
                @if ($childCount > 0)
                    <p class="mt-1 text-sm text-red-800">It has {{ $childCount }} sub-location(s). Delete or move those first.</p>
                @elseif ($profileCount > 0)
                    <p class="mt-1 text-sm text-red-800">{{ $profileCount }} profile(s) are listed here. Move them to another location first.</p>
                @else
                    <p class="mt-1 text-sm text-red-800">Removes the location, its SEO content and its alternate names. This can't be undone.</p>
                    <form method="POST" action="{{ route('seo.locations.destroy', $location) }}" class="mt-4" onsubmit="return confirm('Delete {{ $location->name }}? This cannot be undone.');">
                        @csrf @method('DELETE')
                        <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Delete {{ $location->name }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
