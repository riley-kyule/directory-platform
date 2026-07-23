<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Moderation</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif

            @if($appeals->isNotEmpty())
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-amber-200 bg-amber-50 p-5"><h3 class="font-bold text-amber-950">Pending appeals</h3></div>
                    <div class="divide-y">@foreach($appeals as $appeal)<div class="grid gap-4 p-5 lg:grid-cols-[1fr_1fr_2fr]"><div><p class="font-semibold">{{ $appeal->profile->display_name }}</p><p class="text-sm text-gray-500">{{ $appeal->appellant->email }} · {{ $appeal->created_at->format('j M Y H:i') }}</p></div><p class="text-sm text-gray-700">{{ $appeal->reason }}</p><form method="POST" action="{{ route('staff.moderation.appeals.review', $appeal) }}" class="grid gap-3 sm:grid-cols-[auto_1fr_auto]">@csrf @method('PATCH')<select name="decision" required class="rounded-md border-gray-300 text-sm"><option value="approve">Approve and restore</option><option value="reject">Reject appeal</option></select><input name="resolution" required minlength="10" class="rounded-md border-gray-300 text-sm" placeholder="Required decision rationale"><button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Record</button></form></div>@endforeach</div>
                </section>
            @endif

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <form method="GET" class="flex flex-wrap gap-4 border-b p-5">
                    <select name="status" class="rounded-md border-gray-300 text-sm"><option value="">All statuses</option>@foreach(['new','in_review','resolved','dismissed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
                    <select name="priority" class="rounded-md border-gray-300 text-sm"><option value="">All priorities</option><option value="urgent" @selected(($filters['priority'] ?? '') === 'urgent')>Urgent</option><option value="normal" @selected(($filters['priority'] ?? '') === 'normal')>Normal</option></select>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Filter cases</button>
                </form>
                <div class="divide-y">
                    @forelse($reports as $report)
                        <a href="{{ route('staff.moderation.show', $report) }}" class="grid gap-3 p-5 hover:bg-gray-50 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-center">
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $report->priority === 'urgent' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">{{ $report->priority }}</span>
                            <div><p class="font-semibold">{{ $report->categoryLabel() }}</p><p class="text-sm text-gray-500">{{ $report->public_id }}</p></div>
                            <div><p class="font-medium">{{ $report->profile->display_name }}</p><p class="text-sm capitalize text-gray-500">{{ str($report->profile->status->value)->replace('_', ' ') }}</p></div>
                            <div class="text-right"><p class="text-sm font-semibold capitalize">{{ str($report->status)->replace('_', ' ') }}</p><p class="text-xs text-gray-500">{{ $report->created_at->format('j M Y H:i') }}</p></div>
                        </a>
                    @empty<p class="p-8 text-center text-sm text-gray-500">No moderation reports match these filters.</p>@endforelse
                </div>
                @if($reports->hasPages())<div class="border-t p-5">{{ $reports->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
