@php
    $profile = null;
    $form = [];
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Add listing</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">The change could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('staff.directory.store') }}" class="space-y-6" x-data="{ ownerMode: '{{ old('owner_mode', 'existing_user') }}' }">
                @csrf

                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Who does this listing belong to?</h3>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="owner_mode" value="existing_user" x-model="ownerMode"> Existing provider account</label>
                        <label class="flex items-center gap-2"><input type="radio" name="owner_mode" value="new_user" x-model="ownerMode"> Create a new provider account</label>
                        <label class="flex items-center gap-2"><input type="radio" name="owner_mode" value="agency" x-model="ownerMode"> Attach to an agency's roster</label>
                    </div>

                    <div x-show="ownerMode === 'existing_user'" x-cloak class="mt-5 space-y-4">
                        <div class="flex gap-2">
                            <x-text-input name="owner_q" class="block w-full max-w-sm" placeholder="Search provider by name or email" :value="$ownerSearch" />
                            <x-secondary-button type="submit" formaction="{{ route('staff.directory.create') }}" formmethod="GET">Search</x-secondary-button>
                        </div>
                        @if ($ownerSearch !== '')
                            <div class="divide-y rounded-md border">
                                @forelse ($existingProviders as $provider)
                                    <label class="flex items-center gap-3 p-3 text-sm"><input type="radio" name="existing_user_id" value="{{ $provider->id }}" @checked(old('existing_user_id') == $provider->id) required> <span class="font-medium">{{ $provider->name }}</span> <span class="text-gray-500">{{ $provider->email }}</span></label>
                                @empty
                                    <p class="p-3 text-sm text-gray-500">No provider accounts match "{{ $ownerSearch }}".</p>
                                @endforelse
                            </div>
                        @endif
                        <x-input-error :messages="$errors->get('existing_user_id')" class="mt-2" />
                    </div>

                    <div x-show="ownerMode === 'new_user'" x-cloak class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><x-input-label for="new_user_name" value="Provider name" /><x-text-input id="new_user_name" name="new_user_name" class="mt-1 block w-full" :value="old('new_user_name')" /><x-input-error :messages="$errors->get('new_user_name')" class="mt-2" /></div>
                        <div><x-input-label for="new_user_email" value="Provider email" /><x-text-input id="new_user_email" name="new_user_email" type="email" class="mt-1 block w-full" :value="old('new_user_email')" /><x-input-error :messages="$errors->get('new_user_email')" class="mt-2" /></div>
                        <div class="sm:col-span-2"><x-input-label for="new_user_password" value="Initial password" /><x-text-input id="new_user_password" name="new_user_password" type="text" class="mt-1 block w-full" /><p class="mt-1 text-xs text-gray-500">Share this with the provider directly — they can change it after logging in.</p><x-input-error :messages="$errors->get('new_user_password')" class="mt-2" /></div>
                    </div>

                    <div x-show="ownerMode === 'agency'" x-cloak class="mt-5">
                        <x-input-label for="agency_id" value="Agency" />
                        <select id="agency_id" name="agency_id" class="mt-1 block w-full max-w-sm rounded-md border-gray-300">
                            <option value="">Choose agency</option>
                            @foreach ($agencies as $agency)
                                <option value="{{ $agency->id }}" @selected(old('agency_id') == $agency->id)>{{ $agency->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('agency_id')" class="mt-2" />
                    </div>
                </section>

                @include('onboarding.partials.profile-fields')

                <div class="flex justify-end"><x-primary-button>Create listing</x-primary-button></div>
            </form>
        </div>
    </div>
</x-app-layout>
