<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><h2 class="text-xl font-semibold leading-tight text-gray-800">Moderation case</h2><a href="{{ route('staff.moderation.index') }}" class="text-sm font-semibold text-indigo-600">Back to queue</a></div></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif

            <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                <section class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center gap-3"><span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $report->priority === 'urgent' ? 'bg-red-100 text-red-800' : 'bg-gray-100' }}">{{ $report->priority }}</span><span class="text-sm font-semibold capitalize">{{ str($report->status)->replace('_', ' ') }}</span><span class="text-sm text-gray-500">{{ $report->public_id }}</span></div>
                    <div><p class="text-sm text-gray-500">Category</p><h3 class="text-xl font-bold">{{ $report->categoryLabel() }}</h3></div>
                    <div><p class="text-sm text-gray-500">Reporter follow-up</p><p class="font-medium">{{ $report->reporter_email }}</p><p class="text-xs text-gray-500">{{ $report->reporter?->email ? 'Registered account' : 'Guest report' }}</p></div>
                    <div><p class="text-sm text-gray-500">Confidential details</p><p class="mt-2 whitespace-pre-line rounded-md bg-gray-50 p-4 text-gray-800">{{ $report->details }}</p></div>
                    <div class="border-t pt-5"><p class="text-sm text-gray-500">Profile</p><p class="font-bold">{{ $report->profile->display_name }}</p><p class="text-sm text-gray-600">{{ $report->profile->sublocation->name }}, {{ $report->profile->primaryLocation->name }} · {{ str($report->profile->status->value)->replace('_', ' ')->title() }}</p><a href="{{ route('staff.directory.show', $report->profile) }}" class="mt-2 inline-block text-sm font-semibold text-indigo-600">Open staff profile record</a></div>
                </section>

                <aside class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-bold">Record case action</h3>
                    <form method="POST" action="{{ route('staff.moderation.update', $report) }}" class="mt-5 space-y-4">@csrf @method('PATCH')
                        <label class="block text-sm"><span class="font-medium">Action</span><select name="action" required class="mt-1 block w-full rounded-md border-gray-300"><option value="assign_to_me">Assign to me</option><option value="start_review">Start review</option><option value="note">Add case note</option><option value="resolve">Resolve without enforcement</option><option value="dismiss">Dismiss report</option><option value="make_private">Emergency takedown / make private</option><option value="ban">Ban profile</option></select></label>
                        <label class="block text-sm"><span class="font-medium">Required rationale</span><textarea name="reason" rows="7" required class="mt-1 block w-full rounded-md border-gray-300"></textarea></label>
                        <x-primary-button>Record action</x-primary-button>
                    </form>
                </aside>
            </div>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-5"><h3 class="font-bold">Case action history</h3></div>
                <div class="divide-y">@forelse($report->actions->sortByDesc('created_at') as $action)<div class="grid gap-2 p-4 text-sm sm:grid-cols-[1fr_1fr_2fr]"><div><p class="font-semibold">{{ str($action->action)->replace('_', ' ')->title() }}</p><p class="text-xs text-gray-500">{{ $action->created_at->format('j M Y H:i') }}</p></div><p>{{ $action->actor?->email ?? 'System' }}</p><p class="text-gray-700">{{ $action->reason }}</p></div>@empty<p class="p-6 text-sm text-gray-500">No actions recorded.</p>@endforelse</div>
            </section>
        </div>
    </div>
</x-app-layout>
