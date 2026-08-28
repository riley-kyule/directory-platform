<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Reviews</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <form method="GET" class="flex flex-wrap gap-4 border-b p-5">
                    <select name="status" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                        @foreach (['pending', 'published', 'rejected'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ str($option)->title() }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="divide-y">
                    @forelse ($reviews as $review)
                        <div class="grid gap-4 p-5 lg:grid-cols-[1fr_2fr_auto]">
                            <div>
                                <p class="font-semibold">{{ $review->profile->display_name }}</p>
                                <p class="text-sm text-gray-500">{{ $review->reviewer_name ?: 'Anonymous' }} &middot; {{ $review->rating }}/5 &middot; {{ $review->created_at->format('j M Y H:i') }}</p>
                            </div>
                            <p class="whitespace-pre-line text-sm text-gray-700">{{ $review->body }}</p>
                            @if ($status === 'pending')
                                <div class="flex flex-col gap-2 lg:items-end">
                                    <form method="POST" action="{{ route('staff.reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.reviews.update', $review) }}" class="flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="reject">
                                        <input type="text" name="reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 text-sm">
                                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                                    </form>
                                </div>
                            @else
                                <div class="text-right text-sm text-gray-500">
                                    <p class="font-semibold capitalize">{{ $review->status }}</p>
                                    <p>{{ $review->moderatedBy?->name ?? 'Unknown' }} &middot; {{ $review->moderated_at?->format('j M Y H:i') }}</p>
                                    @if ($review->moderation_reason)<p class="mt-1 italic">"{{ $review->moderation_reason }}"</p>@endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="p-8 text-center text-sm text-gray-500">No {{ $status }} reviews.</p>
                    @endforelse
                </div>
                @if ($reviews->hasPages())<div class="border-t p-5">{{ $reviews->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
