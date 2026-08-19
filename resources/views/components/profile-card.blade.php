@props(['profile', 'isNew' => false, 'priority' => false])
@php
    $image = $profile->images->first();
    $package = $profile->currentPackageAssignment?->package?->code;
    $call = $profile->contacts->firstWhere('type', 'call');
    $activity = $profile->activityLabel();
    $agency = $profile->currentAgency->first();
@endphp
<article class="group overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
    <a href="{{ route('directory.profiles.show', $profile->slug) }}" class="relative block aspect-[4/5] overflow-hidden bg-gradient-to-br from-stone-200 via-stone-100 to-rose-100">
        @if ($image?->publicUrl('card'))
            <img src="{{ $image->publicUrl('card') }}" srcset="{{ $image->responsiveSrcset(['thumb', 'card', 'profile']) }}" sizes="(min-width: 1280px) 280px, (min-width: 1024px) 30vw, (min-width: 420px) 50vw, 100vw" alt="{{ $profile->display_name }} in {{ $profile->microLocation?->name ?? $profile->sublocation->name }}" width="{{ $image->derivatives['card']['width'] ?? 640 }}" height="{{ $image->derivatives['card']['height'] ?? 800 }}" loading="{{ $priority ? 'eager' : 'lazy' }}" fetchpriority="{{ $priority ? 'high' : 'low' }}" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <div class="grid h-full place-items-center px-6 text-center">
                <span class="text-5xl font-black text-stone-300">{{ str($profile->display_name)->substr(0, 1)->upper() }}</span>
            </div>
        @endif
        <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
            <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-white">✓ Verified</span>
            @if ($package === 'vip')<span class="rounded-full bg-amber-300 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-amber-950">VIP</span>@endif
            @if ($package === 'premium')<span class="rounded-full bg-violet-600 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-white">Premium</span>@endif
            @if ($isNew)<span class="rounded-full bg-rose-500 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-white">New</span>@endif
        </div>
        @if ($activity)
            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-stone-950/80 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
                <span class="h-2 w-2 rounded-full {{ $activity === 'online' ? 'bg-emerald-400' : 'bg-amber-300' }}"></span>
                {{ $activity === 'online' ? 'Online' : 'Recently active' }}
            </span>
        @endif
    </a>
    <div class="p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate text-lg font-bold"><a href="{{ route('directory.profiles.show', $profile->slug) }}">{{ $profile->display_name }}</a></h3>
                <p class="mt-0.5 truncate text-sm text-stone-500">@if($profile->microLocation){{ $profile->microLocation->name }}, @endif{{ $profile->sublocation->name }}, {{ $profile->primaryLocation->name }}</p>
                <p class="mt-1 truncate text-xs font-medium text-stone-400">{{ $agency ? 'Agency managed · '.$agency->name : 'Independent listing' }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-600">{{ $profile->date_of_birth->age }}</span>
        </div>
        <div class="mt-4 flex gap-2">
            <a href="{{ route('directory.profiles.show', $profile->slug) }}" class="min-w-0 flex-1 truncate rounded-xl border border-stone-200 px-3 py-3 text-center text-sm font-semibold transition hover:border-stone-400">View profile</a>
            @if ($call)
                <a href="tel:{{ $call->normalized_value }}" title="Call {{ $profile->display_name }}" data-conversion data-profile="{{ $profile->public_id }}" data-channel="call" data-placement="profile_card" class="min-w-0 flex-1 truncate rounded-xl bg-rose-500 px-3 py-3 text-center text-sm font-bold text-white transition hover:bg-rose-600">Call {{ $profile->display_name }}</a>
            @endif
        </div>
    </div>
</article>
