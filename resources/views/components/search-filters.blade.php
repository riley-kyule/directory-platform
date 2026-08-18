@props([
    'filters' => [],
    'searchCities',
    'searchNeighbourhoods',
    'searchTaxonomies',
])
@php
    $advancedOpen = collect($filters)
        ->only(['gender', 'ethnicity', 'build', 'bust_size', 'availability', 'services', 'sort'])
        ->reject(fn ($value, $key) => $key === 'sort' && in_array($value, [null, '', 'recommended'], true))
        ->filter()
        ->isNotEmpty();
    $hasAppliedFilters = collect($filters)
        ->reject(fn ($value, $key) => $key === 'sort' && in_array($value, [null, '', 'recommended'], true))
        ->filter()
        ->isNotEmpty();
@endphp

<form method="GET" action="{{ route('directory.search') }}" {{ $attributes->merge(['class' => 'rounded-2xl border border-stone-200 bg-white p-5 shadow-sm']) }} x-data="{ city: @js($filters['city'] ?? ''), advanced: @js($advancedOpen) }">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <label class="sm:col-span-2 lg:col-span-2">
            <span class="text-sm font-bold text-stone-700">Name or profile text</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" minlength="2" maxlength="100" class="mt-1 block w-full rounded-xl border-stone-300" placeholder="Name, service or profile text">
        </label>
        <label class="lg:col-span-2">
            <span class="text-sm font-bold text-stone-700">City</span>
            <select name="city" x-model="city" @change="$refs.neighbourhood.value = ''" class="mt-1 block w-full rounded-xl border-stone-300">
                <option value="">Any city</option>
                @foreach ($searchCities as $city)
                    <option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="lg:col-span-2">
            <span class="text-sm font-bold text-stone-700">Neighbourhood</span>
            <select name="neighbourhood" x-ref="neighbourhood" class="mt-1 block w-full rounded-xl border-stone-300">
                <option value="">Any neighbourhood</option>
                @foreach ($searchNeighbourhoods as $neighbourhood)
                    <option value="{{ $neighbourhood->slug }}" x-show="!city || city === '{{ $neighbourhood->parent?->slug }}'" @selected(($filters['neighbourhood'] ?? '') === $neighbourhood->slug)>
                        {{ $neighbourhood->name }}{{ $neighbourhood->parent ? ' · '.$neighbourhood->parent->name : '' }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4">
        <button type="button" @click="advanced = ! advanced" :aria-expanded="advanced.toString()" class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-bold text-stone-700 hover:bg-stone-100">
            <span x-text="advanced ? 'Fewer filters' : 'More filters'">More filters</span>
            <svg class="h-4 w-4 transition" :class="advanced && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
        </button>
        <div class="flex flex-1 items-center justify-end gap-2">
            @if ($hasAppliedFilters)
                <a href="{{ route('directory.search') }}" class="rounded-full px-4 py-2.5 text-sm font-bold text-stone-600 hover:bg-stone-100">Clear</a>
            @endif
            <button class="rounded-full bg-stone-950 px-6 py-2.5 text-sm font-bold text-white hover:bg-rose-600">Search profiles</button>
        </div>
    </div>

    <div x-show="advanced" x-cloak class="mt-5 border-t border-stone-100 pt-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <label>
                <span class="text-sm font-bold text-stone-700">Sort results</span>
                <select name="sort" class="mt-1 block w-full rounded-xl border-stone-300">
                    <option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>Recommended</option>
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest first</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name A–Z</option>
                </select>
            </label>
        </div>

        @if ($searchTaxonomies->get('service', collect())->isNotEmpty())
            <fieldset class="mt-5">
                <legend class="text-sm font-bold text-stone-700">Services</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($searchTaxonomies->get('service') as $service)
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-stone-300 px-3 py-2 text-sm transition hover:border-stone-500">
                            <input type="checkbox" name="services[]" value="{{ $service->slug }}" @checked(in_array($service->slug, $filters['services'] ?? [], true)) class="rounded border-stone-300 text-rose-600 focus:ring-rose-500">
                            {{ $service->label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </div>
</form>
