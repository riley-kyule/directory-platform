<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Packages</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">The change could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Packages</h3>
                <p class="mt-1 text-sm text-gray-600">Package codes and listing sections remain fixed; display names, image and video limits, order, and availability are editable.</p>
                <div class="mt-6 space-y-4">
                    @foreach ($packages as $package)
                        <form method="POST" action="{{ route('admin.settings.packages.update', $package) }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                            @csrf
                            @method('PATCH')
                            <div><x-input-label value="Code" /><p class="mt-2 font-mono text-sm uppercase text-gray-700">{{ $package->code }}</p></div>
                            <div><x-input-label :for="'package_name_'.$package->id" value="Display name" /><x-text-input :id="'package_name_'.$package->id" name="name" class="mt-1 block w-full" :value="$package->name" required /></div>
                            <div><x-input-label :for="'image_limit_'.$package->id" value="Image limit" /><x-text-input :id="'image_limit_'.$package->id" name="image_limit" type="number" min="1" max="50" class="mt-1 block w-full" :value="$package->image_limit" required /></div>
                            <div><x-input-label :for="'video_limit_'.$package->id" value="Video limit" /><x-text-input :id="'video_limit_'.$package->id" name="video_limit" type="number" min="0" max="20" class="mt-1 block w-full" :value="$package->video_limit" required /></div>
                            <div><x-input-label :for="'package_order_'.$package->id" value="Display order" /><x-text-input :id="'package_order_'.$package->id" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" :value="$package->display_order" required /></div>
                            <div class="flex items-center justify-between gap-4 lg:block"><label class="text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($package->is_active)> Active</label><x-primary-button class="lg:mt-3">Save package</x-primary-button></div>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Package durations</h3>
                <div class="mt-6 space-y-4">
                    @foreach ($durations as $duration)
                        <form method="POST" action="{{ route('admin.settings.durations.update', $duration) }}" class="grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                            @csrf
                            @method('PATCH')
                            <div><x-input-label :for="'duration_label_'.$duration->id" value="Label" /><x-text-input :id="'duration_label_'.$duration->id" name="label" class="mt-1 block w-full" :value="$duration->label" required /></div>
                            <div><x-input-label :for="'duration_days_'.$duration->id" value="Days" /><x-text-input :id="'duration_days_'.$duration->id" name="duration_days" type="number" min="1" max="3650" class="mt-1 block w-full" :value="$duration->duration_days" required /></div>
                            <div><x-input-label :for="'duration_order_'.$duration->id" value="Display order" /><x-text-input :id="'duration_order_'.$duration->id" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" :value="$duration->display_order" required /></div>
                            <div class="flex items-center justify-between gap-4 lg:block"><label class="text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($duration->is_active)> Active</label><x-primary-button class="lg:mt-3">Save duration</x-primary-button></div>
                        </form>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('admin.settings.durations.store') }}" class="mt-8 grid gap-4 rounded-lg border-2 border-dashed border-gray-300 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    @csrf
                    <div><x-input-label for="new_duration_label" value="New duration label" /><x-text-input id="new_duration_label" name="label" class="mt-1 block w-full" placeholder="45 days" required /></div>
                    <div><x-input-label for="new_duration_days" value="Days" /><x-text-input id="new_duration_days" name="duration_days" type="number" min="1" max="3650" class="mt-1 block w-full" required /></div>
                    <div><x-input-label for="new_duration_order" value="Display order" /><x-text-input id="new_duration_order" name="display_order" type="number" min="0" max="1000" class="mt-1 block w-full" value="100" required /></div>
                    <div><input type="hidden" name="is_active" value="1"><x-primary-button>Add duration</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
