<x-guest-layout>
    <h1 class="text-2xl font-bold text-gray-900">Save your recovery codes</h1>
    <p class="mt-2 text-sm text-gray-600">Store these codes in a secure password manager. Each code works once, and they will not be shown again.</p>

    <div class="mt-6 grid grid-cols-1 gap-2 rounded-md border border-amber-200 bg-amber-50 p-4 sm:grid-cols-2">
        @foreach($recoveryCodes as $code)<code class="font-mono text-sm font-bold text-amber-950">{{ $code }}</code>@endforeach
    </div>

    <a href="{{ route('dashboard') }}" class="mt-6 inline-flex w-full justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">I have saved the codes</a>
</x-guest-layout>
