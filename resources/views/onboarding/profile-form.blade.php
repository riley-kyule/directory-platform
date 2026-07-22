<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Create profile') }}</h2></x-slot>

    <div class="py-12" x-data="{ additionalOpen: {{ $errors->hasAny(['hair_color_option_id', 'hair_length_option_id', 'height_cm', 'weight_kg', 'smoker', 'language_ids', 'website_url', 'instagram_handle', 'snapchat_handle', 'tiktok_handle']) ? 'true' : 'false' }} }">
        <form method="POST" action="{{ route('onboarding.profiles.store') }}" class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @csrf

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4" role="alert">
                    <p class="font-medium text-red-800">Please correct the highlighted fields.</p>
                </div>
            @endif

            @if ($locations->isEmpty() || ($taxonomies['ethnicity'] ?? collect())->isEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Staff must publish locations and configure ethnicity options before a profile can be submitted.
                </div>
            @endif

            <section class="grid gap-5 bg-white p-6 shadow-sm sm:rounded-lg md:grid-cols-2">
                <h3 class="text-lg font-semibold text-gray-900 md:col-span-2">Required information</h3>

                <div><x-input-label for="display_name" value="Name" /><x-text-input id="display_name" name="display_name" class="mt-1 block w-full" :value="old('display_name')" required /><x-input-error :messages="$errors->get('display_name')" class="mt-2" /></div>
                <div><x-input-label for="phone" value="Phone number" /><x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone')" placeholder="+254..." required /><x-input-error :messages="$errors->get('phone')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-input-label for="description" value="About / Bio" /><textarea id="description" name="description" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>

                <div><x-input-label for="primary_location_id" value="Location" /><select id="primary_location_id" name="primary_location_id" required class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose location</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(old('primary_location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('primary_location_id')" class="mt-2" /></div>
                <div><x-input-label for="sublocation_id" value="Sub-location" /><select id="sublocation_id" name="sublocation_id" required class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose sub-location</option>@foreach ($sublocations as $location)<option value="{{ $location->id }}" data-parent="{{ $location->parent_id }}" @selected(old('sublocation_id') == $location->id)>{{ $location->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('sublocation_id')" class="mt-2" /></div>

                @foreach ([['gender_option_id', 'gender', 'Gender'], ['ethnicity_option_id', 'ethnicity', 'Ethnicity'], ['build_option_id', 'build', 'Build'], ['bust_size_option_id', 'bust_size', 'Bust size']] as [$field, $type, $label])
                    <div><x-input-label :for="$field" :value="$label" /><select id="{{ $field }}" name="{{ $field }}" @required($field !== 'bust_size_option_id') class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose {{ strtolower($label) }}</option>@foreach ($taxonomies[$type] ?? [] as $option)<option value="{{ $option->id }}" @selected(old($field) == $option->id)>{{ $option->label }}</option>@endforeach</select><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                @endforeach

                <div><x-input-label for="date_of_birth" value="Date of birth" /><x-text-input id="date_of_birth" name="date_of_birth" type="date" max="{{ now()->subYears(18)->toDateString() }}" class="mt-1 block w-full" :value="old('date_of_birth')" required /><x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" /></div>

                <fieldset><legend class="text-sm font-medium text-gray-700">Availability</legend><div class="mt-2 flex gap-5"><label><input type="checkbox" name="allows_incall" value="1" @checked(old('allows_incall'))> Incall</label><label><input type="checkbox" name="allows_outcall" value="1" @checked(old('allows_outcall'))> Outcall</label></div><x-input-error :messages="$errors->get('availability')" class="mt-2" /></fieldset>

                <fieldset class="md:col-span-2"><legend class="text-sm font-medium text-gray-700">Services</legend><div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($taxonomies['service'] ?? [] as $option)<label><input type="checkbox" name="service_ids[]" value="{{ $option->id }}" @checked(in_array($option->id, old('service_ids', [])))> {{ $option->label }}</label>@endforeach</div><x-input-error :messages="$errors->get('service_ids')" class="mt-2" /></fieldset>

                <fieldset class="md:col-span-2"><legend class="text-sm font-medium text-gray-700">Phone availability</legend><div class="mt-2 flex flex-wrap gap-5"><label><input type="checkbox" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled'))> Available on WhatsApp</label><label><input type="checkbox" name="telegram_phone_enabled" value="1" @checked(old('telegram_phone_enabled'))> Available on Telegram</label></div></fieldset>
                <div><x-input-label for="telegram_username" value="Telegram username (if phone is not used)" /><x-text-input id="telegram_username" name="telegram_username" class="mt-1 block w-full" :value="old('telegram_username')" /><x-input-error :messages="$errors->get('telegram_username')" class="mt-2" /></div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <button type="button" @click="additionalOpen = ! additionalOpen" :aria-expanded="additionalOpen" class="flex w-full items-center justify-between text-left text-lg font-semibold text-gray-900"><span>Additional information</span><span aria-hidden="true" x-text="additionalOpen ? '−' : '+'"></span></button>
                <div x-show="additionalOpen" x-cloak class="mt-6 grid gap-5 md:grid-cols-2">
                    @foreach ([['hair_color_option_id', 'hair_color', 'Hair color'], ['hair_length_option_id', 'hair_length', 'Hair length'], ['sexual_orientation_option_id', 'sexual_orientation', 'Sexual orientation']] as [$field, $type, $label])
                        <div><x-input-label :for="$field" :value="$label" /><select id="{{ $field }}" name="{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Not specified</option>@foreach ($taxonomies[$type] ?? [] as $option)<option value="{{ $option->id }}" @selected(old($field) == $option->id)>{{ $option->label }}</option>@endforeach</select><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                    <div><x-input-label for="height_cm" value="Height (cm)" /><x-text-input id="height_cm" name="height_cm" type="number" min="100" max="250" class="mt-1 block w-full" :value="old('height_cm')" /></div>
                    <div><x-input-label for="weight_kg" value="Weight (kg)" /><x-text-input id="weight_kg" name="weight_kg" type="number" min="30" max="300" step="0.1" class="mt-1 block w-full" :value="old('weight_kg')" /></div>
                    <div><x-input-label for="smoker" value="Smoker" /><select id="smoker" name="smoker" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Not specified</option><option value="1" @selected(old('smoker') === '1')>Yes</option><option value="0" @selected(old('smoker') === '0')>No</option></select></div>
                    <fieldset class="md:col-span-2"><legend class="text-sm font-medium text-gray-700">Languages spoken</legend><div class="mt-2 flex flex-wrap gap-4">@foreach ($taxonomies['language'] ?? [] as $option)<label><input type="checkbox" name="language_ids[]" value="{{ $option->id }}" @checked(in_array($option->id, old('language_ids', [])))> {{ $option->label }}</label>@endforeach</div></fieldset>
                    @foreach ([['website_url', 'Website', 'url'], ['instagram_handle', 'Instagram', 'text'], ['snapchat_handle', 'Snapchat', 'text'], ['tiktok_handle', 'TikTok', 'text']] as [$field, $label, $type])
                        <div><x-input-label :for="$field" :value="$label" /><x-text-input :id="$field" :name="$field" :type="$type" class="mt-1 block w-full" :value="old($field)" /><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-5 bg-white p-6 shadow-sm sm:rounded-lg md:grid-cols-3">
                <h3 class="text-lg font-semibold text-gray-900 md:col-span-3">Optional starting rate</h3>
                <div><x-input-label for="rate_currency" value="Currency" /><x-text-input id="rate_currency" name="rate_currency" maxlength="3" class="mt-1 block w-full uppercase" :value="old('rate_currency')" placeholder="KES" /></div>
                <div><x-input-label for="rate_period_option_id" value="Period" /><select id="rate_period_option_id" name="rate_period_option_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose period</option>@foreach ($taxonomies['rate_period'] ?? [] as $option)<option value="{{ $option->id }}" @selected(old('rate_period_option_id') == $option->id)>{{ $option->label }}</option>@endforeach</select></div>
                <div><x-input-label for="rate_price" value="Price" /><x-text-input id="rate_price" name="rate_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('rate_price')" /></div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Choose package</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">@foreach ($packages as $package)<label class="rounded-lg border p-4"><input type="radio" name="requested_package_id" value="{{ $package->id }}" @checked(old('requested_package_id') == $package->id) required> <span class="font-semibold">{{ $package->name }}</span><span class="mt-1 block text-sm text-gray-600">Up to {{ $package->image_limit }} images</span></label>@endforeach</div>
                <x-input-error :messages="$errors->get('requested_package_id')" class="mt-2" />
            </section>

            <div class="flex justify-end"><x-primary-button>Submit for review</x-primary-button></div>
        </form>
    </div>
</x-app-layout>
