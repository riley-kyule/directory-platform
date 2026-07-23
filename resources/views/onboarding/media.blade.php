<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Media for {{ $profile->display_name }}</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>@endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h3 class="text-lg font-semibold">Profile images</h3><p class="mt-1 text-sm text-gray-600">{{ $profile->images->count() }} of {{ $limit }} package slots used. JPEG, PNG, or WebP; maximum 50 MB.</p></div>
                    <a href="{{ route('onboarding.index') }}" class="text-sm font-medium text-indigo-600">Back to onboarding</a>
                </div>
                @if ($canManage && $profile->images->count() < $limit)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('profiles.media.store', $profile) }}" class="mt-6 flex flex-wrap items-end gap-4">@csrf
                        <div class="min-w-0 flex-1"><x-input-label for="image" value="Choose image" /><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full text-sm"></div>
                        <x-policy-acceptances :policies="$requiredPolicies" class="w-full" />
                        <x-primary-button>Upload securely</x-primary-button>
                    </form>
                @elseif (! $canManage)
                    <p class="mt-5 rounded-md bg-gray-50 p-4 text-sm text-gray-600">Media cannot be changed while this profile is in its current state.</p>
                @endif
            </section>

            <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($profile->images as $image)
                    <article class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        @if (in_array($image->status, ['pending_review', 'approved']) && isset($image->derivatives['card']))
                            <img src="{{ route('profiles.media.preview', [$profile, $image, 'card']) }}" alt="Media submitted for {{ $profile->display_name }}" width="{{ $image->derivatives['card']['width'] }}" height="{{ $image->derivatives['card']['height'] }}" class="aspect-[4/5] w-full object-cover">
                        @else
                            <div class="flex aspect-[4/5] items-center justify-center bg-gray-100 p-5 text-center text-sm text-gray-500">{{ str($image->status)->replace('_', ' ')->title() }}</div>
                        @endif
                        <div class="flex items-center justify-between p-4"><span class="text-sm capitalize text-gray-600">{{ str($image->status)->replace('_', ' ') }}</span>@if ($canManage)<form method="POST" action="{{ route('profiles.media.destroy', [$profile, $image]) }}">@csrf @method('DELETE')<button class="text-sm font-medium text-red-600">Remove</button></form>@endif</div>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed bg-white p-8 text-sm text-gray-600 sm:col-span-2 lg:col-span-3">No images uploaded yet. At least one successfully processed image is required before review submission.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
