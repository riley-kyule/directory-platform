<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Moderation</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('staff.moderation.metrics') }}" class="text-sm font-semibold text-indigo-600">View moderation metrics &rarr;</a>
            @if(session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif

            @if($sla['overdue_urgent_reports'] + $sla['overdue_normal_reports'] + $sla['overdue_appeals'] > 0)
                <section class="rounded-lg border border-red-200 bg-red-50 p-5" role="alert">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div><h3 class="font-bold text-red-950">Moderation response targets have been exceeded</h3><p class="mt-1 text-sm text-red-800">{{ $sla['overdue_urgent_reports'] }} urgent, {{ $sla['overdue_normal_reports'] }} normal, and {{ $sla['overdue_appeals'] }} appeal {{ Str::plural('case', $sla['overdue_appeals']) }} require escalation.</p></div>
                        <a href="{{ route('staff.moderation.index', ['sla' => 'overdue']) }}" class="inline-flex min-h-11 items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Show overdue cases</a>
                    </div>
                </section>
            @endif

            @if($appeals->isNotEmpty())
                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-amber-200 bg-amber-50 p-5"><h3 class="font-bold text-amber-950">Pending appeals</h3></div>
                    <div class="divide-y">@foreach($appeals as $appeal)<div class="grid gap-4 p-5 lg:grid-cols-[1fr_1fr_2fr]"><div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold">{{ $appeal->profile->display_name }}</p>@if($appeal->slaState() === 'overdue')<span class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-800">Overdue by {{ $appeal->slaDeadline()->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true, 2) }}</span>@endif</div><p class="text-sm text-gray-500">{{ $appeal->appellant->email }} · {{ $appeal->created_at->format('j M Y H:i') }}</p></div><p class="text-sm text-gray-700">{{ $appeal->reason }}</p><form method="POST" action="{{ route('staff.moderation.appeals.review', $appeal) }}" class="grid gap-3 sm:grid-cols-[auto_1fr_auto]">@csrf @method('PATCH')<select name="decision" required class="rounded-md border-gray-300 text-sm"><option value="approve">Approve and restore</option><option value="reject">Reject appeal</option></select><input name="resolution" required minlength="10" class="rounded-md border-gray-300 text-sm" placeholder="Required decision rationale"><button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Record</button></form></div>@endforeach</div>
                </section>
            @endif

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <form method="GET" class="flex flex-wrap gap-4 border-b p-5">
                    <select name="status" class="rounded-md border-gray-300 text-sm"><option value="">All statuses</option>@foreach(['new','in_review','resolved','dismissed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
                    <select name="priority" class="rounded-md border-gray-300 text-sm"><option value="">All priorities</option><option value="urgent" @selected(($filters['priority'] ?? '') === 'urgent')>Urgent</option><option value="normal" @selected(($filters['priority'] ?? '') === 'normal')>Normal</option></select>
                    <select name="sla" class="rounded-md border-gray-300 text-sm"><option value="">Any SLA state</option><option value="overdue" @selected(($filters['sla'] ?? '') === 'overdue')>Overdue only</option></select>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Filter cases</button>
                </form>
                <div class="divide-y">
                    @forelse($reports as $report)
                        <a href="{{ route('staff.moderation.show', $report) }}" class="grid gap-3 p-5 hover:bg-gray-50 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-center">
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $report->priority === 'urgent' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">{{ $report->priority }}</span>
                            <div><p class="font-semibold">{{ $report->categoryLabel() }}</p><p class="text-sm text-gray-500">{{ $report->public_id }}</p></div>
                            <div><p class="font-medium">{{ $report->profile->display_name }}</p><p class="text-sm capitalize text-gray-500">{{ str($report->profile->status->value)->replace('_', ' ') }}</p></div>
                            <div class="text-right"><p class="text-sm font-semibold capitalize">{{ str($report->status)->replace('_', ' ') }}</p>@if($report->slaState() !== 'closed')<p class="text-xs font-semibold {{ $report->slaState() === 'overdue' ? 'text-red-700' : ($report->slaState() === 'due_soon' ? 'text-amber-700' : 'text-green-700') }}">{{ $report->slaState() === 'overdue' ? 'Overdue by ' : 'Due in ' }}{{ $report->slaDeadline()->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true, 2) }}</p>@endif<p class="text-xs text-gray-500">{{ $report->created_at->format('j M Y H:i') }}</p></div>
                        </a>
                    @empty<p class="p-8 text-center text-sm text-gray-500">No moderation reports match these filters.</p>@endforelse
                </div>
                @if($reports->hasPages())<div class="border-t p-5">{{ $reports->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
