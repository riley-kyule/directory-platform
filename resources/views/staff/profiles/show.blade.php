<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Review {{ $packageRequest->profile->display_name }}</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <section class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><p class="text-sm text-gray-500">Name</p><p class="font-medium">{{ $packageRequest->profile->display_name }}</p></div>
                        <div><p class="text-sm text-gray-500">Location</p><p class="font-medium">{{ $packageRequest->profile->primaryLocation->name }} / {{ $packageRequest->profile->sublocation->name }}@if($packageRequest->profile->microLocation) / {{ $packageRequest->profile->microLocation->name }}@endif</p></div>
                        <div><p class="text-sm text-gray-500">Date of birth</p><p class="font-medium">{{ $packageRequest->profile->date_of_birth->format('j M Y') }}</p></div>
                        <div><p class="text-sm text-gray-500">Requested package</p><p class="font-medium">{{ $packageRequest->requestedPackage->name }}</p></div>
                    </div>
                    <div class="mt-5"><p class="text-sm text-gray-500">About</p><p class="mt-1 whitespace-pre-line text-gray-800">{{ $packageRequest->profile->description }}</p></div>
                    <div class="mt-5"><p class="text-sm text-gray-500">Services</p><p class="mt-1 text-gray-800">{{ $packageRequest->profile->services->pluck('label')->join(', ') }}</p></div>
                    <div class="mt-5"><p class="text-sm text-gray-500">Contact methods</p><ul class="mt-1 space-y-1">@foreach ($packageRequest->profile->contacts as $contact)<li>{{ str($contact->type)->replace('_', ' ')->title() }}: {{ $contact->display_value }}</li>@endforeach</ul></div>
                    <div class="mt-5"><p class="text-sm text-gray-500">Media</p><div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">@forelse ($packageRequest->profile->images as $image)@if (in_array($image->status, ['pending_review', 'approved']) && isset($image->derivatives['thumb']))<img src="{{ route('profiles.media.preview', [$packageRequest->profile, $image, 'thumb']) }}" alt="Submitted media" class="aspect-[4/5] w-full rounded-md object-cover">@else<div class="flex aspect-[4/5] items-center justify-center rounded-md bg-gray-100 p-2 text-center text-xs text-gray-500">{{ str($image->status)->replace('_', ' ')->title() }}</div>@endif @empty<p class="col-span-full text-sm text-red-700">No media submitted.</p>@endforelse</div></div>
                </div>
            </section>

            <aside class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold">Decision</h3>
                <form method="POST" action="{{ route('staff.profiles.update', $packageRequest) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PATCH')
                    <div><x-input-label for="decision" value="Decision" /><select id="decision" name="decision" required class="mt-1 block w-full rounded-md border-gray-300"><option value="approve" @selected(old('decision') === 'approve')>Approve and activate</option><option value="reject" @selected(old('decision') === 'reject')>Reject</option></select><x-input-error :messages="$errors->get('decision')" class="mt-2" /></div>
                    <div><x-input-label for="assigned_package_id" value="Assigned package" /><select id="assigned_package_id" name="assigned_package_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose package</option>@foreach ($packages as $package)<option value="{{ $package->id }}" @selected(old('assigned_package_id', $packageRequest->requested_package_id) == $package->id)>{{ $package->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('assigned_package_id')" class="mt-2" /></div>
                    <div><x-input-label for="duration_option_id" value="Duration" /><select id="duration_option_id" name="duration_option_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Choose duration</option>@foreach ($durations as $duration)<option value="{{ $duration->id }}" @selected(old('duration_option_id') == $duration->id)>{{ $duration->label }}</option>@endforeach</select><x-input-error :messages="$errors->get('duration_option_id')" class="mt-2" /></div>
                    <div><x-input-label for="reason" value="Decision reason" /><textarea id="reason" name="reason" rows="5" required class="mt-1 block w-full rounded-md border-gray-300">{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2" /></div>
                    <x-primary-button>Save decision</x-primary-button>
                </form>
            </aside>
        </div>
    </div>
</x-app-layout>
