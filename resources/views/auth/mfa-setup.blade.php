<x-guest-layout>
    <h1 class="text-2xl font-bold text-gray-900">Secure your staff account</h1>
    <p class="mt-2 text-sm text-gray-600">Admin, CSR, and SEO accounts require an authenticator code before privileged access is granted.</p>

    <ol class="mt-6 list-decimal space-y-4 pl-5 text-sm text-gray-700">
        <li>Open a standards-compatible authenticator app and choose to add an account manually.</li>
        <li>
            Enter this setup key:
            <code class="mt-2 block break-all rounded-md bg-gray-100 p-3 font-mono text-gray-900">{{ $secret }}</code>
        </li>
        <li>
            If your app accepts provisioning links, copy this URI:
            <code class="mt-2 block max-h-28 overflow-y-auto break-all rounded-md bg-gray-100 p-3 text-xs">{{ $provisioningUri }}</code>
        </li>
        <li>Enter the current six-digit code below.</li>
    </ol>

    <form method="POST" action="{{ route('mfa.confirm') }}" class="mt-6 space-y-4">@csrf
        <div>
            <x-input-label for="code" value="Authenticator code" />
            <x-text-input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-1 block w-full text-center font-mono text-xl tracking-[.4em]" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>
        <x-primary-button class="w-full justify-center">Confirm and enable MFA</x-primary-button>
    </form>
</x-guest-layout>
