<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Taxonomy options</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold">Add taxonomy option</h3>
                <p class="mt-1 text-sm text-gray-600">These options power the dropdowns providers use when filling out gender, ethnicity, services, and other profile attributes.</p>
                <form method="POST" action="{{ route('seo.taxonomies.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                    <div><x-input-label for="type" value="Type" /><select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300">@foreach (['gender', 'ethnicity', 'hair_color', 'hair_length', 'bust_size', 'build', 'sexual_orientation', 'language', 'service', 'rate_period'] as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                    <div><x-input-label for="label" value="Label" /><x-text-input id="label" name="label" class="mt-1 block w-full" :value="old('label')" required /><x-input-error :messages="$errors->get('label')" class="mt-2" /></div>
                    <div><x-input-label for="country_code" value="Country code (optional)" /><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code')" /><p class="mt-1 text-xs text-gray-500">Leave blank for an option available in every country.</p></div>
                    <div><x-input-label for="sort_order" value="Sort order" /><x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', 100)" required /><p class="mt-1 text-xs text-gray-500">Lower numbers appear first in dropdowns.</p></div>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_bust_size" value="1" @checked(old('requires_bust_size'))> Require bust size for this gender</label>
                    <div class="sm:col-span-2"><x-primary-button>Add option</x-primary-button></div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold">Existing options</h3>
                <p class="mt-1 text-sm text-gray-600">Grouped by type, in the order they appear in dropdowns.</p>
                <p class="mt-1 text-sm text-gray-600">Type and country are fixed once created. Options in use by at least one profile can't be deleted — deactivate them instead.</p>
                @php $grouped = $taxonomyOptions->groupBy('type'); @endphp
                <div class="mt-6 space-y-6">
                    @forelse ($grouped as $type => $options)
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ str($type)->replace('_', ' ')->title() }}</h4>
                            <div class="mt-2 space-y-2">
                                @foreach ($options as $option)
                                    <div class="rounded-md border border-gray-200 p-3">
                                        <form method="POST" action="{{ route('seo.taxonomies.update', $option) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                                            @csrf
                                            @method('PATCH')
                                            <div class="lg:col-span-2">
                                                <x-input-label :for="'label_'.$option->id" value="Label" />
                                                <x-text-input :id="'label_'.$option->id" name="label" class="mt-1 block w-full" :value="$option->label" required />
                                            </div>
                                            <div>
                                                <x-input-label :for="'sort_order_'.$option->id" value="Sort order" />
                                                <x-text-input :id="'sort_order_'.$option->id" name="sort_order" type="number" min="0" max="65535" class="mt-1 block w-full" :value="$option->sort_order" required />
                                            </div>
                                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($option->is_active)> Active</label>
                                            @if ($type === 'gender')
                                                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_bust_size" value="1" @checked($option->settings['requires_bust_size'] ?? false)> Requires bust size</label>
                                            @endif
                                            <div class="text-xs text-gray-500">{{ $option->country_code ?: 'All countries' }}</div>
                                            <div><x-primary-button>Save</x-primary-button></div>
                                        </form>
                                        <form method="POST" action="{{ route('seo.taxonomies.delete', $option) }}" onsubmit="return confirm('Delete &quot;{{ $option->label }}&quot;? This cannot be undone.');" class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800">Delete</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No taxonomy options configured yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
