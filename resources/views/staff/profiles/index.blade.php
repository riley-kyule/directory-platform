<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Profile reviews</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 p-6"><h3 class="text-lg font-semibold">Pending activation</h3><p class="mt-1 text-sm text-gray-600">Oldest submissions are shown first.</p></div>
                <div class="divide-y divide-gray-200">
                    @forelse ($requests as $packageRequest)
                        <a href="{{ route('staff.profiles.show', $packageRequest) }}" class="grid gap-2 p-5 hover:bg-gray-50 sm:grid-cols-4 sm:items-center">
                            <div><p class="font-medium text-gray-900">{{ $packageRequest->profile->display_name }}</p><p class="text-sm text-gray-500">{{ $packageRequest->requestedBy->email }}</p></div>
                            <p class="text-sm text-gray-700">{{ $packageRequest->profile->primaryLocation->name }}</p>
                            <p class="text-sm font-medium text-gray-700">{{ $packageRequest->requestedPackage->name }}</p>
                            <p class="text-sm text-gray-500 sm:text-right">{{ $packageRequest->requested_at->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="p-8 text-center text-sm text-gray-600">There are no profiles waiting for activation.</p>
                    @endforelse
                </div>
            </section>
            {{ $requests->links() }}
        </div>
    </div>
</x-app-layout>
