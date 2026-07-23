@props([
    'filters' => [],
    'searchCities',
    'searchNeighbourhoods',
    'searchTaxonomies',
])

<form method="GET" action="{{ route('directory.search') }}" {{ $attributes->merge(['class' => 'rounded-2xl border border-stone-200 bg-white p-5 shadow-sm']) }} x-data="{ city: '{{ $filters['city'] ?? '' }}' }">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="sm:col-span-2">
            <span class="text-sm font-bold text-stone-700">Name or profile text</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" minlength="2" maxlength="100" class="mt-1 block w-full rounded-xl border-stone-300" placeholder="Search profiles">
        </label>
        <label>
            <span class="text-sm font-bold text-stone-700">City</span>
            <select name="city" x-model="city" class="mt-1 block w-full rounded-xl border-stone-300">
                <option value="">Any city</option>
                @foreach ($searchCities as $city)
                    <option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="text-sm font-bold text-stone-700">Neighbourhood</span>
            <select name="neighbourhood" class="mt-1 block w-full rounded-xl border-stone-300">
                <option value="">Any neighbourhood</option>
                @foreach ($searchNeighbourhoods as $neighbourhood)
                    <option value="{{ $neighbourhood->slug }}" x-show="!city || city === '{{ $neighbourhood->parent?->slug }}'" @selected(($filters['neighbourhood'] ?? '') === $neighbourhood->slug)>
                        {{ $neighbourhood->name }}{{ $neighbourhood->parent ? ' · '.$neighbourhood->parent->name : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        @foreach (['gender' => 'Gender', 'ethnicity' => 'Ethnicity', 'build' => 'Build', 'bust_size' => 'Bust size'] as $type => $label)
            <label>
                <span class="text-sm font-bold text-stone-700">{{ $label }}</span>
                <select name="{{ $type }}" class="mt-1 block w-full rounded-xl border-stone-300">
                    <option value="">Any</option>
                    @foreach ($searchTaxonomies->get($type, collect()) as $option)
                        <option value="{{ $option->slug }}" @selected(($filters[$type] ?? '') === $option->slug)>{{ $option->label }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <label>
            <span class="text-sm font-bold text-stone-700">Availability</span>
            <select name="availability" class="mt-1 block w-full rounded-xl border-stone-300">
                <option value="">Any</option>
                <option value="incall" @selected(($filters['availability'] ?? '') === 'incall')>Incall</option>
                <option value="outcall" @selected(($filters['availability'] ?? '') === 'outcall')>Outcall</option>
                <option value="both" @selected(($filters['availability'] ?? '') === 'both')>Incall and outcall</option>
            </select>
        </label>
    </div>

    @if ($searchTaxonomies->get('service', collect())->isNotEmpty())
        <fieldset class="mt-5">
            <legend class="text-sm font-bold text-stone-700">Services</legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($searchTaxonomies->get('service') as $service)
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-stone-300 px-3 py-2 text-sm">
                        <input type="checkbox" name="services[]" value="{{ $service->slug }}" @checked(in_array($service->slug, $filters['services'] ?? [], true)) class="rounded border-stone-300 text-rose-600 focus:ring-rose-500">
                        {{ $service->label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endif

    <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
        <a href="{{ route('directory.search') }}" class="rounded-full px-4 py-2 text-sm font-bold text-stone-600 hover:bg-stone-100">Clear filters</a>
        <button class="rounded-full bg-stone-950 px-6 py-2.5 text-sm font-bold text-white hover:bg-rose-600">Search profiles</button>
    </div>
</form>
