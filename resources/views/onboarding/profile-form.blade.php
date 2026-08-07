@php
    $editing = $profile !== null;
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $editing ? __('Edit profile') : __('Create profile') }}</h2></x-slot>

    <div class="py-12">
        <form method="POST" action="{{ $editing ? route('provider.profiles.update', $profile) : route('onboarding.profiles.store') }}" class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @csrf
            @if ($editing) @method('PATCH') @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4" role="alert">
                    <p class="font-medium text-red-800">Please correct the highlighted fields.</p>
                </div>
            @endif

            @include('onboarding.partials.profile-fields')

            <div class="flex justify-end"><x-primary-button>{{ $editing ? 'Save profile' : 'Save and continue to media' }}</x-primary-button></div>
        </form>
    </div>
</x-app-layout>
