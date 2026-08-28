@php
    $breadcrumbItems = [
        ['name' => 'Home', 'url' => route('directory.home')],
        ['name' => $profile->primaryLocation->name, 'url' => route('directory.cities.show', $profile->primaryLocation->slug)],
        ['name' => $profile->sublocation->name, 'url' => route('directory.neighbourhoods.show', [$profile->primaryLocation->slug, $profile->sublocation->slug])],
    ];
    if ($profile->microLocation) {
        $breadcrumbItems[] = [
            'name' => $profile->microLocation->name,
            'url' => route('directory.micro-locations.show', [$profile->primaryLocation->slug, $profile->sublocation->slug, $profile->microLocation->slug]),
        ];
    }
    $breadcrumbItems[] = ['name' => $profile->display_name, 'url' => $canonicalUrl];
    $profileImages = $profile->images->map(fn ($image) => $image->publicUrl('profile'))->filter()->map(fn ($url) => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url))->values()->all();
    $primaryImage = $profile->images->first();
    $profileImageSizes = '(min-width: 1024px) 58vw, (min-width: 640px) 50vw, 100vw';
    $schemas = [
        \App\Support\JsonLd::breadcrumbs($breadcrumbItems),
        \App\Support\JsonLd::profilePage(
            $canonicalUrl,
            $profile->display_name,
            $metaDescription,
            $profile->primaryLocation->name,
            $profile->microLocation?->name ?? $profile->sublocation->name,
            $profileImages,
            $profile->languages->pluck('label')->all(),
            $reviewStats,
        ),
    ];
@endphp
<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots" :structured-data="\App\Support\JsonLd::script($schemas)" :social-image="$socialImage" social-type="profile" :preload-image="$primaryImage?->publicUrl('profile')" :preload-image-srcset="$primaryImage?->responsiveSrcset()" :preload-image-sizes="$primaryImage ? $profileImageSizes : null" :profile-view-id="$profile->public_id">
    @if (session('report_status'))
        <div class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8"><div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('report_status') }}</div></div>
    @endif
    @php
        $package = $profile->currentPackageAssignment?->package?->code;
        $agency = $profile->currentAgency->first();
    @endphp
    <div class="mx-auto max-w-7xl px-4 pb-24 pt-8 sm:px-6 lg:px-8 lg:py-12">
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
                <div x-data="{
                    selectedImage: null,
                    returnFocus: null,
                    imageCount: {{ $profile->images->count() }},
                    openGallery(index, trigger) {
                        this.selectedImage = index;
                        this.returnFocus = trigger;
                        this.$nextTick(() => this.$refs.galleryClose?.focus());
                    },
                    closeGallery() {
                        this.selectedImage = null;
                        this.$nextTick(() => this.returnFocus?.focus());
                    },
                    stepGallery(direction) {
                        this.selectedImage = (this.selectedImage + direction + this.imageCount) % this.imageCount;
                    },
                    trapGalleryTab(event) {
                        const controls = [...this.$refs.galleryDialog.querySelectorAll('button:not([disabled])')];
                        if (controls.length === 0) return;
                        const first = controls[0];
                        const last = controls[controls.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                        if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                    },
                }" x-effect="document.body.classList.toggle('overflow-hidden', selectedImage !== null)" @keydown.escape.window="if (selectedImage !== null) closeGallery()">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @forelse ($profile->images as $image)
                            @php($imageSlot = $loop->first ? 'profile' : 'card')
                            <button type="button" @click="openGallery({{ $loop->index }}, $event.currentTarget)" aria-label="Open image {{ $loop->iteration }} of {{ $profile->images->count() }}" class="group relative overflow-hidden rounded-2xl text-left focus:outline-none focus:ring-4 focus:ring-rose-300 {{ $loop->first ? 'sm:row-span-2' : '' }}">
                                <img src="{{ $image->publicUrl($imageSlot) }}" srcset="{{ $image->responsiveSrcset() }}" sizes="(min-width: 1024px) 58vw, (min-width: 640px) 50vw, 100vw" alt="{{ $profile->display_name }} profile image {{ $loop->iteration }}" width="{{ $image->derivatives[$imageSlot]['width'] ?? 640 }}" height="{{ $image->derivatives[$imageSlot]['height'] ?? 800 }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'low' }}" decoding="async" class="aspect-[4/5] h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                                <span class="absolute bottom-3 right-3 rounded-full bg-stone-950/75 px-3 py-1.5 text-xs font-bold text-white opacity-0 backdrop-blur transition group-hover:opacity-100 group-focus:opacity-100">View image</span>
                            </button>
                        @empty
                            <div class="grid aspect-[4/5] place-items-center rounded-2xl bg-gradient-to-br from-stone-200 to-rose-100 text-7xl font-black text-stone-300 sm:col-span-2">{{ str($profile->display_name)->substr(0, 1)->upper() }}</div>
                        @endforelse
                    </div>

                    @if ($profile->images->isNotEmpty())
                        <div x-ref="galleryDialog" x-show="selectedImage !== null" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-stone-950/95 p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="{{ $profile->display_name }} image gallery" tabindex="-1" @click.self="closeGallery()" @keydown.tab="trapGalleryTab($event)" @keydown.arrow-left.prevent="stepGallery(-1)" @keydown.arrow-right.prevent="stepGallery(1)">
                            <button x-ref="galleryClose" type="button" @click="closeGallery()" class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20" aria-label="Close image gallery">&times;</button>
                            @foreach ($profile->images as $image)
                                <img x-show="selectedImage === {{ $loop->index }}" src="{{ $image->publicUrl('full') ?? $image->publicUrl('profile') ?? $image->publicUrl('card') }}" alt="{{ $profile->display_name }} enlarged profile image {{ $loop->iteration }}" loading="lazy" decoding="async" class="max-h-[88vh] max-w-full rounded-xl object-contain shadow-2xl">
                            @endforeach
                            @if ($profile->images->count() > 1)
                                <div class="absolute inset-x-4 bottom-5 flex items-center justify-center gap-3">
                                    <button type="button" @click="stepGallery(-1)" class="min-h-11 rounded-full bg-white px-5 py-2 text-sm font-bold text-stone-950">Previous</button>
                                    <span class="rounded-full bg-stone-950/70 px-3 py-2 text-xs font-semibold text-white" aria-live="polite"><span x-text="selectedImage + 1"></span> / {{ $profile->images->count() }}</span>
                                    <button type="button" @click="stepGallery(1)" class="min-h-11 rounded-full bg-white px-5 py-2 text-sm font-bold text-stone-950">Next</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($profile->videos->isNotEmpty())
                    <section class="mt-10">
                        <h2 class="text-2xl font-black">Videos</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($profile->videos as $video)
                                @if ($video->publicUrl())
                                    <video controls preload="none" playsinline class="aspect-video w-full rounded-2xl bg-black shadow-sm"
                                           @if ($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif>
                                        <source src="{{ $video->publicUrl() }}" type="{{ $video->mime_type }}">
                                    </video>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

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
                        @if ($profile->verification_status === 'verified')<span class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">Verified</span>@endif
                        @if ($package === 'vip')<span class="rounded-full bg-amber-300 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-950">VIP</span>@endif
                        @if ($package === 'premium')<span class="rounded-full bg-violet-600 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">Premium</span>@endif
                        @if ($profile->last_activated_at?->gte(now()->subDays($newProfileDays)))<span class="rounded-full bg-rose-500 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">New</span>@endif
                    </div>
                    <h1 class="mt-4 text-4xl font-black tracking-tight">{{ $profile->display_name }}</h1>
                    <p class="mt-2 text-stone-500">@if($profile->microLocation){{ $profile->microLocation->name }}, @endif{{ $profile->sublocation->name }}, {{ $profile->primaryLocation->name }}</p>
                    @if ($agency)
                        <p class="mt-2 text-sm font-semibold text-stone-600">Represented by <a href="{{ route('directory.agencies.show', $agency->slug) }}" class="text-rose-600 underline">{{ $agency->name }}</a></p>
                    @else
                        <p class="mt-2 text-sm font-semibold text-stone-600">Independent listing</p>
                    @endif

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
                            <a href="{{ $contact['href'] }}" data-conversion data-profile="{{ $profile->public_id }}" data-channel="{{ $type }}" data-placement="profile_page" @if (in_array($type, ['whatsapp', 'telegram'])) target="_blank" rel="noopener noreferrer" @endif class="rounded-xl {{ $type === 'call' ? 'bg-rose-500 text-white' : 'bg-stone-100 text-stone-900' }} px-3 py-3 text-center text-sm font-bold transition hover:opacity-80">{{ $contact['label'] }}</a>
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

        <section class="mt-16 border-t border-stone-200 pt-10" aria-labelledby="reviews">
            @if (session('review_status'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('review_status') }}</div>
            @endif
            <div class="mb-6">
                <h2 id="reviews" class="text-2xl font-black tracking-tight sm:text-3xl">Reviews</h2>
                @if ($reviewStats['count'] > 0)
                    <p class="mt-1 text-sm text-stone-500">{{ number_format($reviewStats['average'], 1) }} ★ average from {{ $reviewStats['count'] }} review{{ $reviewStats['count'] === 1 ? '' : 's' }}</p>
                    @if ($reviewStats['shown'] < $reviewStats['count'])<p class="mt-1 text-xs text-stone-400">Showing the latest {{ $reviewStats['shown'] }} reviews.</p>@endif
                @else
                    <p class="mt-1 text-sm text-stone-500">No reviews yet — be the first.</p>
                @endif
            </div>

            @if ($reviews->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($reviews as $review)
                        <div class="rounded-2xl border border-stone-200 bg-white p-5">
                            <div class="flex items-center justify-between gap-4">
                                <span class="font-bold">{{ $review->reviewer_name ?: 'Anonymous' }}</span>
                                <span class="text-amber-500" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $review->body }}</p>
                            <p class="mt-2 text-xs text-stone-400">{{ $review->created_at->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 text-center">
                <a href="{{ route('directory.profiles.reviews.create', $profile) }}" class="inline-block rounded-full bg-rose-500 px-6 py-3 text-sm font-bold text-white transition hover:opacity-80">Leave a review</a>
            </div>
        </section>

        <div class="border-t border-stone-200 pt-8 text-center">
            <a href="{{ route('directory.profiles.report.create', $profile) }}" class="text-sm font-bold text-stone-600 underline hover:text-rose-600">Report a concern about this profile</a>
        </div>
    </div>

    @if ($contactLinks)
        <nav class="fixed inset-x-0 bottom-0 z-50 grid grid-flow-col border-t border-stone-200 bg-white/95 p-2 shadow-2xl backdrop-blur md:hidden" aria-label="Contact {{ $profile->display_name }}">
            @foreach ($contactLinks as $type => $contact)<a href="{{ $contact['href'] }}" data-conversion data-profile="{{ $profile->public_id }}" data-channel="{{ $type }}" data-placement="mobile_bar" @if (in_array($type, ['whatsapp', 'telegram'])) target="_blank" rel="noopener noreferrer" @endif class="rounded-lg px-2 py-3 text-center text-xs font-bold {{ $type === 'call' ? 'bg-rose-500 text-white' : '' }}">{{ $contact['label'] }}</a>@endforeach
        </nav>
    @endif
</x-public-layout>
