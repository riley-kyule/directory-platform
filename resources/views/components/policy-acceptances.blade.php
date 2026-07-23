@props(['policies'])

@if ($policies->isNotEmpty())
    <fieldset {{ $attributes->merge(['class' => 'space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4']) }}>
        <legend class="px-1 text-sm font-semibold text-gray-800">Required policies</legend>
        @foreach ($policies as $policy)
            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input
                    type="checkbox"
                    name="policy_acceptances[]"
                    value="{{ $policy->id }}"
                    @checked(in_array($policy->id, array_map('intval', old('policy_acceptances', [])), true))
                    required
                    class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                >
                <span>
                    I accept the
                    <a href="{{ $policy->publicRoute() }}" target="_blank" rel="noopener" class="font-semibold text-indigo-600 underline hover:text-indigo-500">
                        {{ $policy->title }}
                    </a>
                    <span class="text-gray-500">(version {{ $policy->version }})</span>
                </span>
            </label>
        @endforeach
        <x-input-error :messages="$errors->get('policy_acceptances')" class="mt-2" />
    </fieldset>
@endif
