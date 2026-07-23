<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Account Type -->
        <fieldset class="mt-4" x-data="{ accountType: '{{ old('account_type', 'member') }}' }">
            <legend class="text-sm font-medium text-gray-700">{{ __('I am registering as') }}</legend>

            <div class="mt-2 flex gap-6">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="account_type" value="member" x-model="accountType" required>
                    <span>{{ __('Member') }}</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="account_type" value="provider" x-model="accountType" required>
                    <span>{{ __('Provider') }}</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('account_type')" class="mt-2" />

            <div class="mt-4" x-show="accountType === 'provider'" x-cloak>
                <x-input-label for="provider_type" :value="__('Provider type')" />
                <select id="provider_type" name="provider_type" :required="accountType === 'provider'"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('Choose provider type') }}</option>
                    <option value="independent" @selected(old('provider_type') === 'independent')>{{ __('Independent') }}</option>
                    <option value="agency" @selected(old('provider_type') === 'agency')>{{ __('Agency') }}</option>
                </select>
                <x-input-error :messages="$errors->get('provider_type')" class="mt-2" />
            </div>
        </fieldset>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-policy-acceptances :policies="$requiredPolicies" class="mt-5" />

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
