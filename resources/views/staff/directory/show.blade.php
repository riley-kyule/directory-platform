<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Manage {{ $profile->display_name }}</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="grid gap-6 lg:grid-cols-[1.3fr_.7fr]">
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><h3 class="text-2xl font-bold text-gray-900">{{ $profile->display_name }}</h3><p class="mt-1 text-sm text-gray-600">{{ $profile->sublocation->name }}, {{ $profile->primaryLocation->name }}</p></div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold capitalize text-gray-700">{{ str($profile->status->value)->replace('_', ' ') }}</span>
                    </div>
                    <dl class="mt-6 grid gap-4 border-y py-5 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">Owner</dt><dd class="mt-1 font-medium">{{ $profile->owner?->email ?? $profile->currentAgency->first()?->owner?->email ?? 'Unassigned' }}</dd></div>
                        <div><dt class="text-gray-500">Agency</dt><dd class="mt-1 font-medium">{{ $profile->currentAgency->first()?->name ?? 'Independent' }}</dd></div>
                        <div><dt class="text-gray-500">Current expiry</dt><dd class="mt-1 font-medium">{{ $profile->expires_at?->format('d M Y H:i') ?? 'None' }}</dd></div>
                        <div><dt class="text-gray-500">Last activated</dt><dd class="mt-1 font-medium">{{ $profile->last_activated_at?->format('d M Y H:i') ?? 'Never' }}</dd></div>
                    </dl>
                    <h4 class="mt-6 font-semibold text-gray-900">Package history</h4>
                    <div class="mt-3 divide-y rounded-md border">@forelse ($profile->packageAssignments->sortByDesc('starts_at') as $assignment)<div class="flex justify-between gap-4 p-3 text-sm"><div><p class="font-semibold">{{ $assignment->package->name }}</p><p class="text-xs text-gray-500">{{ str($assignment->assignment_source)->replace('_', ' ') }}</p></div><div class="text-right"><p class="capitalize">{{ $assignment->status }}</p><p class="text-xs text-gray-500">{{ $assignment->starts_at->format('d M Y') }} – {{ $assignment->expires_at->format('d M Y') }}</p></div></div>@empty<p class="p-4 text-sm text-gray-500">No package history.</p>@endforelse</div>
                </section>

                <aside class="space-y-5">
                    @if ($profile->status === \App\Enums\ProfileStatus::Active)
                        <form method="POST" action="{{ route('staff.directory.update', $profile) }}" class="space-y-4 bg-white p-5 shadow-sm sm:rounded-lg">@csrf @method('PATCH')
                            <h3 class="font-bold text-gray-900">Make profile private</h3>
                            <label class="block text-sm"><span class="text-gray-700">Action</span><select name="action" class="mt-1 block w-full rounded-md border-gray-300" required><option value="deactivate">Deactivate profile</option><option value="remove_package">Remove active package</option><option value="ban">Ban profile</option></select></label>
                            <label class="block text-sm"><span class="text-gray-700">Required reason</span><textarea name="reason" rows="4" class="mt-1 block w-full rounded-md border-gray-300" required></textarea></label>
                            <x-danger-button>Confirm action</x-danger-button>
                        </form>
                    @elseif (in_array($profile->status, [\App\Enums\ProfileStatus::Expired, \App\Enums\ProfileStatus::Deactivated], true))
                        <form method="POST" action="{{ route('staff.directory.update', $profile) }}" class="space-y-4 bg-white p-5 shadow-sm sm:rounded-lg">@csrf @method('PATCH')
                            <input type="hidden" name="action" value="renew">
                            <h3 class="font-bold text-gray-900">Renew and reactivate</h3>
                            <label class="block text-sm"><span class="text-gray-700">Package</span><select name="package_id" class="mt-1 block w-full rounded-md border-gray-300" required>@foreach ($packages as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach</select></label>
                            <label class="block text-sm"><span class="text-gray-700">Duration</span><select name="duration_option_id" class="mt-1 block w-full rounded-md border-gray-300" required>@foreach ($durations as $duration)<option value="{{ $duration->id }}">{{ $duration->label }}</option>@endforeach</select></label>
                            <label class="block text-sm"><span class="text-gray-700">Required reason</span><textarea name="reason" rows="4" class="mt-1 block w-full rounded-md border-gray-300" required></textarea></label>
                            <x-primary-button>Renew profile</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('staff.directory.update', $profile) }}" class="space-y-3 bg-white p-5 shadow-sm sm:rounded-lg">@csrf @method('PATCH')<input type="hidden" name="action" value="ban"><h3 class="font-bold">Ban instead</h3><textarea name="reason" rows="3" class="block w-full rounded-md border-gray-300" placeholder="Required reason" required></textarea><x-danger-button>Ban profile</x-danger-button></form>
                    @elseif ($profile->status === \App\Enums\ProfileStatus::Banned)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800">This profile is banned. Reactivation requires a future explicit unban workflow and cannot be performed as a renewal.</div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-5 text-sm text-gray-700">This profile is in the {{ str($profile->status->value)->replace('_', ' ') }} workflow. Complete that workflow before applying lifecycle management actions.</div>
                    @endif
                </aside>
            </div>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-5"><h3 class="font-bold text-gray-900">Audit history</h3></div>
                <div class="divide-y">@forelse ($audits as $audit)<div class="grid gap-2 p-4 text-sm sm:grid-cols-[1fr_1fr_1.5fr]"><div><p class="font-semibold">{{ $audit->action }}</p><p class="text-xs text-gray-500">{{ $audit->created_at->format('d M Y H:i') }}</p></div><p class="text-gray-600">{{ $audit->actor?->email ?? 'System' }}</p><p class="text-gray-700">{{ $audit->reason }}</p></div>@empty<p class="p-6 text-sm text-gray-500">No audit records for this profile.</p>@endforelse</div>
            </section>
        </div>
    </div>
</x-app-layout>
