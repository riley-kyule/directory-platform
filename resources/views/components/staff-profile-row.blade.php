@props(['profile'])
@php
    $assignment = $profile->currentPackageAssignment
        ?? $profile->packageAssignments->sortByDesc('starts_at')->first();
@endphp
<a href="{{ route('staff.directory.show', $profile) }}" class="grid gap-2 p-4 transition hover:bg-gray-50 sm:grid-cols-[1.4fr_1fr_.8fr_.8fr] sm:items-center">
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
