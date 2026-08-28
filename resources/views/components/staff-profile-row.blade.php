@props(['profile'])
@php
    $assignment = $profile->currentPackageAssignment
        ?? $profile->packageAssignments->sortByDesc('starts_at')->first();
@endphp
<div class="grid gap-2 p-4 transition hover:bg-gray-50 sm:grid-cols-[1.4fr_1fr_.8fr_.8fr_auto] sm:items-center">
    <a href="{{ route('staff.directory.show', $profile) }}" class="contents">
        <div class="min-w-0">
            <p class="truncate font-semibold text-gray-900">{{ $profile->display_name }}</p>
            <p class="truncate text-xs text-gray-500">{{ $profile->owner?->email ?? $profile->currentAgency->first()?->owner?->email ?? 'No active owner relationship' }}</p>
        </div>
        <p class="text-sm text-gray-600">@if($profile->microLocation){{ $profile->microLocation->name }}, @endif{{ $profile->sublocation->name }}, {{ $profile->primaryLocation->name }}</p>
        <div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold capitalize text-gray-700">{{ str($profile->status->value)->replace('_', ' ') }}</span></div>
        <div class="text-sm sm:text-right">
            <p class="font-semibold text-gray-700">{{ $assignment?->package?->name ?? 'No package' }}</p>
            <p class="text-xs text-gray-500">{{ $profile->expires_at ? ($profile->expires_at->isPast() ? 'Expired '.$profile->expires_at->diffForHumans() : 'Expires '.$profile->expires_at->diffForHumans()) : 'No expiry' }}</p>
        </div>
    </a>
    @can('moderation.manage')
        @if ($profile->status->value !== 'banned')
            <form method="POST" action="{{ route('staff.directory.emergency-takedown', $profile) }}" class="flex items-center gap-2" onsubmit="return confirm('Take this profile down immediately? This bans it right away, bypassing the normal report queue.');">
                @csrf @method('PATCH')
                <input type="text" name="reason" class="w-40 rounded-md border-gray-300 text-xs" placeholder="Reason (optional)">
                <button class="whitespace-nowrap rounded-md bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">Emergency takedown</button>
            </form>
        @endif
    @endcan
</div>
