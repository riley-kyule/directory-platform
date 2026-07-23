<x-guest-layout>
    <h1 class="text-2xl font-bold text-gray-900">Staff security check</h1>
    <p class="mt-2 text-sm text-gray-600">Enter the current six-digit authenticator code. You may also enter one unused recovery code.</p>

    <form method="POST" action="{{ route('mfa.verify') }}" class="mt-6 space-y-4">@csrf
        <div>
            <x-input-label for="credential" value="Authenticator or recovery code" />
            <x-text-input id="credential" name="credential" autocomplete="one-time-code" maxlength="32" required autofocus class="mt-1 block w-full text-center font-mono text-lg tracking-wider" />
            <x-input-error :messages="$errors->get('credential')" class="mt-2" />
        </div>
        <x-primary-button class="w-full justify-center">Continue securely</x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">@csrf
        <button class="text-sm font-medium text-gray-600 underline">Log out instead</button>
    </form>
</x-guest-layout>
