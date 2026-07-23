<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    @php($package = $profile->currentPackageAssignment?->package?->code)
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <nav class="mb-7 text-sm text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('directory.home') }}" class="hover:text-stone-950">Home</a><span class="mx-2">/</span>
            <a href="{{ route('directory.cities.show', $profile->primaryLocation->slug) }}" class="hover:text-stone-950">{{ $profile->primaryLocation->name }}</a><span class="mx-2">/</span>
            <a href="{{ route('directory.neighbourhoods.show', [$profile->primaryLocation->slug, $profile->sublocation->slug]) }}" class="hover:text-stone-950">{{ $profile->sublocation->name }}</a><span class="mx-2">/</span>
            @if ($profile->microLocation)
                <a href="{{ route('directory.micro-locations.show', [$profile->primaryLocation->slug, $profile->sublocation->slug, $profile->microLocation->slug]) }}" class="hover:text-stone-950">{{ $profile->microLocation->name }}</a><span class="mx-2">/</span>
            @endif
            <span>{{ $profile->display_name }}</span>
        </nav>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
            <div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @forelse ($profile->images as $image)
                        @php($imageSlot = $loop->first ? 'profile' : 'card')
                        <img src="{{ $image->publicUrl($imageSlot) }}" alt="{{ $profile->display_name }} profile image {{ $loop->iteration }}" width="{{ $image->derivatives[$imageSlot]['width'] ?? 640 }}" height="{{ $image->derivatives[$imageSlot]['height'] ?? 800 }}" class="aspect-[4/5] w-full rounded-2xl object-cover {{ $loop->first ? 'sm:row-span-2 sm:h-full' : '' }}">
                    @empty
                        <div class="grid aspect-[4/5] place-items-center rounded-2xl bg-gradient-to-br from-stone-200 to-rose-100 text-7xl font-black text-stone-300 sm:col-span-2">{{ str($profile->display_name)->substr(0, 1)->upper() }}</div>
                    @endforelse
                </div>

                <section class="mt-10">
                    <h2 class="text-2xl font-black">About {{ $profile->display_name }}</h2>
                    <p class="mt-4 whitespace-pre-line leading-7 text-stone-700">{{ $profile->description }}</p>
                </section>

                @if ($profile->services->isNotEmpty())
                    <section class="mt-10"><h2 class="text-2xl font-black">Services</h2><div class="mt-4 flex flex-wrap gap-2">@foreach ($profile->services as $service)<span class="rounded-full bg-white px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-stone-200">{{ $service->label }}</span>@endforeach</div></section>
                @endif
            </div>

            <aside>
                <div class="sticky top-24 rounded-3xl border border-stone-200 bg-white p-6 shadow-xl shadow-stone-200/50 sm:p-8">
                    <div class="flex flex-wrap gap-2">
                        @if ($package === 'vip')<span class="rounded-full bg-amber-300 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-950">VIP</span>@endif
                        @if ($package === 'premium')<span class="rounded-full bg-violet-600 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">Premium</span>@endif
                        @if ($profile->last_activated_at?->gte(now()->subDays($newProfileDays)))<span class="rounded-full bg-rose-500 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">New</span>@endif
                    </div>
                    <h1 class="mt-4 text-4xl font-black tracking-tight">{{ $profile->display_name }}</h1>
                    <p class="mt-2 text-stone-500">@if($profile->microLocation){{ $profile->microLocation->name }}, @endif{{ $profile->sublocation->name }}, {{ $profile->primaryLocation->name }}</p>

                    <dl class="mt-7 grid grid-cols-2 gap-x-5 gap-y-5 border-y border-stone-200 py-6 text-sm">
                        <div><dt class="text-stone-400">Age</dt><dd class="mt-1 font-bold">{{ $profile->date_of_birth->age }}</dd></div>
                        <div><dt class="text-stone-400">Gender</dt><dd class="mt-1 font-bold">{{ $profile->gender->label }}</dd></div>
                        <div><dt class="text-stone-400">Ethnicity</dt><dd class="mt-1 font-bold">{{ $profile->ethnicity->label }}</dd></div>
                        <div><dt class="text-stone-400">Build</dt><dd class="mt-1 font-bold">{{ $profile->build->label }}</dd></div>
                        <div><dt class="text-stone-400">Availability</dt><dd class="mt-1 font-bold">{{ collect([$profile->allows_incall ? 'Incall' : null, $profile->allows_outcall ? 'Outcall' : null])->filter()->join(' & ') }}</dd></div>
                        @if ($profile->languages->isNotEmpty())<div><dt class="text-stone-400">Languages</dt><dd class="mt-1 font-bold">{{ $profile->languages->pluck('label')->join(', ') }}</dd></div>@endif
                    </dl>

                    @if ($profile->rates->isNotEmpty())
                        <div class="mt-6"><h2 class="font-black">Rates</h2><div class="mt-3 space-y-2">@foreach ($profile->rates as $rate)<div class="flex justify-between gap-4 text-sm"><span class="text-stone-500">{{ $rate->period->label }}</span><strong>{{ $rate->currency_code }} {{ number_format((float) $rate->price) }}</strong></div>@endforeach</div></div>
                    @endif

                    <div class="mt-7 grid grid-cols-2 gap-2">
                        @foreach ($contactLinks as $type => $contact)
                            <a href="{{ $contact['href'] }}" @if (in_array($type, ['whatsapp', 'telegram'])) target="_blank" rel="noopener noreferrer" @endif class="rounded-xl {{ $type === 'call' ? 'bg-rose-500 text-white' : 'bg-stone-100 text-stone-900' }} px-3 py-3 text-center text-sm font-bold transition hover:opacity-80">{{ $contact['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>

        @if ($relatedProfiles->isNotEmpty())
            <section class="mt-16 border-t border-stone-200 pt-10" aria-labelledby="related-profiles">
                <div class="mb-6">
                    <h2 id="related-profiles" class="text-2xl font-black tracking-tight sm:text-3xl">More profiles near {{ $profile->display_name }}</h2>
                    <p class="mt-1 text-sm text-stone-500">Other active profiles in {{ $profile->primaryLocation->name }}, with {{ $profile->microLocation?->name ?? $profile->sublocation->name }} shown first.</p>
                </div>
                <div class="grid grid-cols-1 gap-5 min-[420px]:grid-cols-2 lg:grid-cols-4">
                    @foreach ($relatedProfiles as $relatedProfile)
                        <x-profile-card :profile="$relatedProfile" :is-new="$relatedProfile->last_activated_at?->gte(now()->subDays($newProfileDays))" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if ($contactLinks)
        <div class="fixed inset-x-0 bottom-0 z-50 grid grid-flow-col border-t border-stone-200 bg-white p-2 shadow-2xl md:hidden">
            @foreach ($contactLinks as $type => $contact)<a href="{{ $contact['href'] }}" @if (in_array($type, ['whatsapp', 'telegram'])) target="_blank" rel="noopener noreferrer" @endif class="rounded-lg px-2 py-3 text-center text-xs font-bold {{ $type === 'call' ? 'bg-rose-500 text-white' : '' }}">{{ $contact['label'] }}</a>@endforeach
        </div>
    @endif
</x-public-layout>
