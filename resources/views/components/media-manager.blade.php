@props([
    'profile',
    'canManage' => false,
    'canUpload' => null,
    'canRemove' => null,
    'photoLimit' => 0,
    'videoLimit' => 0,
    'heading' => 'Photos & videos',
])

@php
    // Upload/retry and removal are enforced by separate permissions at the
    // endpoints (ProfileMediaAccess::canUpload / canRemove). Resolve each from
    // the current viewer so a partially-scoped staff role never sees a button
    // the endpoint will 403. An explicit prop still wins.
    $mediaAccess = app(\App\Services\ProfileMediaAccess::class);
    $mediaViewer = request()->user();
    $canUpload = $canUpload ?? ($mediaViewer && $mediaAccess->canUpload($mediaViewer, $profile));
    $canRemove = $canRemove ?? ($mediaViewer && $mediaAccess->canRemove($mediaViewer, $profile));

    $settings = app(\App\Services\DirectorySettings::class);
    $photoMaxKb = $settings->integer('media.maximum_file_kilobytes');
    $videoMaxKb = $settings->integer('media.video_max_kilobytes');
    $photos = $profile->images;
    $videos = $profile->videos;
    $photoUsed = $photos->whereNotIn('status', ['rejected', 'private'])->count();
    $videoUsed = $videos->whereNotIn('status', ['rejected', 'private'])->count();
    // Photos process in the request now, so only video ever sits in "processing".
    $hasProcessingMedia = $videos->whereIn('status', ['quarantined', 'processing'])->isNotEmpty();
    $badge = fn (string $status) => match ($status) {
        'approved' => 'bg-green-100 text-green-800',
        'pending_review', 'reviewed' => 'bg-blue-100 text-blue-800',
        'processing', 'quarantined' => 'bg-amber-100 text-amber-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $label = fn (string $status) => match ($status) {
        'approved' => 'Live',
        'pending_review', 'reviewed' => 'Ready — goes live with profile',
        'processing', 'quarantined' => 'Processing',
        'rejected' => 'Rejected',
        default => str($status)->replace('_', ' ')->title()->toString(),
    };
@endphp

<section {{ $attributes->merge(['class' => 'space-y-8 bg-white p-6 shadow-sm sm:rounded-lg']) }}
         x-data="mediaManager({
             photoUrl: '{{ route('profiles.media.store', $profile) }}',
             videoUrl: '{{ route('profiles.media.videos.store', $profile) }}',
             photoMaxKb: {{ $photoMaxKb }},
             videoMaxKb: {{ $videoMaxKb }},
             photoSlotsLeft: {{ max(0, $photoLimit - $photoUsed) }},
             videoSlotsLeft: {{ max(0, $videoLimit - $videoUsed) }},
             pollForVideo: {{ $hasProcessingMedia ? 'true' : 'false' }},
         })">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ $heading }}</h3>
        <p class="mt-1 text-sm text-gray-600">
            Photos appear the moment they finish uploading. Videos are inspected and converted to a safe format first, which takes a little longer.
        </p>
    </div>

    <template x-if="uploading || errors.length">
        <div class="space-y-2">
            <template x-if="uploading">
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status" aria-live="polite">
                    <span x-text="progressLabel"></span>
                </div>
            </template>
            <template x-if="errors.length">
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">
                        <template x-for="message in errors" :key="message"><li x-text="message"></li></template>
                    </ul>
                </div>
            </template>
        </div>
    </template>

    @if ($hasProcessingMedia)
        <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status" aria-live="polite">
            A video is still processing. This page updates on its own when it's ready — no action needed.
        </div>
    @endif

    @if ($errors->hasAny(['image', 'video']))
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->get('image') as $message)<li>{{ $message }}</li>@endforeach
                @foreach ($errors->get('video') as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Photos --------------------------------------------------------------- --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="font-semibold text-gray-900">Photos</h4>
            <p class="text-sm text-gray-500">{{ $photoUsed }} of {{ $photoLimit }} slots used · JPEG, PNG or WebP · at least {{ $settings->integer('media.minimum_width') }}px on each side</p>
        </div>

        @if ($canUpload && $photoUsed < $photoLimit)
            <div class="rounded-md border border-dashed border-gray-300 p-4">
                <input type="file" x-ref="photoInput" class="sr-only" multiple
                       accept="image/jpeg,image/png,image/webp"
                       @change="upload('photo', $event.target.files); $event.target.value = ''">
                <button type="button" @click="$refs.photoInput.click()" :disabled="uploading"
                        class="inline-flex min-h-11 items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
                    <span x-text="uploading ? 'Uploading…' : 'Add photos'"></span>
                </button>
                <p class="mt-2 text-xs text-gray-500">Pick one or several at once. <span x-text="photoSlotsLeft"></span> slot(s) left.</p>
            </div>
        @elseif ($canUpload)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">All photo slots for the current package are in use. Remove a photo to add another.</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($photos as $image)
                <article class="overflow-hidden rounded-lg border border-gray-200">
                    @if (in_array($image->status, ['pending_review', 'reviewed', 'approved']) && isset($image->derivatives['card']))
                        <img src="{{ route('profiles.media.preview', [$profile, $image, 'card']) }}"
                             alt="Profile photo" width="{{ $image->derivatives['card']['width'] }}" height="{{ $image->derivatives['card']['height'] }}"
                             class="aspect-[4/5] w-full bg-gray-100 object-cover" loading="lazy" decoding="async">
                    @else
                        <div class="flex aspect-[4/5] items-center justify-center bg-gray-100 p-4 text-center text-sm text-gray-500">
                            {{ $label($image->status) }}
                        </div>
                    @endif
                    <div class="space-y-2 p-3">
                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge($image->status) }}">
                            {{ $label($image->status) }}
                        </span>
                        @if ($image->status === 'rejected' && $image->processing_error)
                            <p class="text-xs text-red-700">{{ $image->processing_error }}</p>
                        @endif
                        @if ($canUpload || $canRemove)
                            <div class="flex items-center gap-3">
                                @if ($image->status === 'rejected' && $canUpload)
                                    <form method="POST" action="{{ route('profiles.media.retry', [$profile, $image]) }}">
                                        @csrf
                                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Retry</button>
                                    </form>
                                @endif
                                @if ($image->status !== 'processing' && $canRemove)
                                    <form method="POST" action="{{ route('profiles.media.destroy', [$profile, $image]) }}"
                                          @submit="return confirm('Remove this photo?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm font-medium text-red-600 hover:text-red-500">Remove</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-600 sm:col-span-2 lg:col-span-3">
                    No photos yet. At least one photo is required before a profile can be submitted for review.
                </p>
            @endforelse
        </div>
    </div>

    {{-- Videos --------------------------------------------------------------- --}}
    <div class="space-y-4 border-t border-gray-100 pt-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="font-semibold text-gray-900">Videos</h4>
            <p class="text-sm text-gray-500">{{ $videoUsed }} of {{ $videoLimit }} slots used · MP4, WebM or MOV</p>
        </div>

        @if ($canUpload && $videoLimit > 0 && $videoUsed < $videoLimit)
            <div class="rounded-md border border-dashed border-gray-300 p-4">
                <input type="file" x-ref="videoInput" class="sr-only" multiple
                       accept="video/mp4,video/webm,video/quicktime"
                       @change="upload('video', $event.target.files); $event.target.value = ''">
                <button type="button" @click="$refs.videoInput.click()" :disabled="uploading"
                        class="inline-flex min-h-11 items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
                    <span x-text="uploading ? 'Uploading…' : 'Add videos'"></span>
                </button>
                <p class="mt-2 text-xs text-gray-500">Pick one or several at once. <span x-text="videoSlotsLeft"></span> slot(s) left.</p>
            </div>
        @elseif ($canUpload && $videoLimit === 0)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">The current package does not include video slots.</p>
        @elseif ($canUpload)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">All video slots for the current package are in use. Remove a video to add another.</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @forelse ($videos as $video)
                <article class="overflow-hidden rounded-lg border border-gray-200">
                    @if (in_array($video->status, ['pending_review', 'reviewed', 'approved']))
                        <video controls preload="metadata" playsinline
                               class="aspect-video w-full bg-black"
                               @if ($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif>
                            <source src="{{ $video->status === 'approved' && $video->publicUrl() ? $video->publicUrl() : route('profiles.media.videos.preview', [$profile, $video]) }}"
                                    type="{{ $video->mime_type }}">
                        </video>
                    @else
                        <div class="flex aspect-video items-center justify-center bg-gray-100 p-4 text-center text-sm text-gray-500">
                            {{ $label($video->status) }}
                        </div>
                    @endif
                    <div class="space-y-2 p-3">
                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge($video->status) }}">
                            {{ $label($video->status) }}
                        </span>
                        @if ($video->duration_seconds)
                            <span class="text-xs text-gray-500">{{ gmdate('i:s', $video->duration_seconds) }}</span>
                        @endif
                        @if ($video->status === 'rejected' && $video->processing_error)
                            <p class="text-xs text-red-700">{{ $video->processing_error }}</p>
                        @endif
                        @if ($canUpload || $canRemove)
                            <div class="flex items-center gap-3">
                                @if ($video->status === 'rejected' && $canUpload)
                                    <form method="POST" action="{{ route('profiles.media.videos.retry', [$profile, $video]) }}">
                                        @csrf
                                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Retry</button>
                                    </form>
                                @endif
                                @if ($video->status !== 'processing' && $canRemove)
                                    <form method="POST" action="{{ route('profiles.media.videos.destroy', [$profile, $video]) }}"
                                          @submit="return confirm('Remove this video?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm font-medium text-red-600 hover:text-red-500">Remove</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-600 sm:col-span-2">
                    No videos yet.
                </p>
            @endforelse
        </div>
    </div>
</section>

@once
    <script>
        window.mediaManager = function (config) {
            return {
                ...config,
                uploading: false,
                errors: [],
                progressLabel: '',
                pollTimer: null,

                init() {
                    if (this.pollForVideo) {
                        this.pollTimer = window.setTimeout(() => window.location.reload(), 9000);
                    }
                },

                async upload(kind, fileList) {
                    const files = Array.from(fileList || []);
                    if (!files.length || this.uploading) return;

                    const isPhoto = kind === 'photo';
                    const url = isPhoto ? this.photoUrl : this.videoUrl;
                    const field = isPhoto ? 'image' : 'video';
                    const maxKb = isPhoto ? this.photoMaxKb : this.videoMaxKb;
                    const slots = isPhoto ? this.photoSlotsLeft : this.videoSlotsLeft;
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;

                    this.errors = [];
                    if (files.length > slots) {
                        this.errors.push(`Only ${slots} more ${isPhoto ? 'photo' : 'video'} slot(s) — the rest were skipped.`);
                        files.length = slots;
                    }
                    if (!files.length) return;

                    this.uploading = true;
                    let added = 0;
                    for (let i = 0; i < files.length; i++) {
                        this.progressLabel = `Uploading ${isPhoto ? 'photo' : 'video'} ${i + 1} of ${files.length}…`;
                        const file = files[i];
                        if (file.size > maxKb * 1024) {
                            this.errors.push(`${file.name}: ${Math.ceil(file.size / 1048576)} MB is over the ${Math.floor(maxKb / 1024)} MB limit.`);
                            continue;
                        }
                        try {
                            const body = new FormData();
                            body.append(field, file);
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body,
                            });
                            if (response.status === 429) {
                                this.errors.push('Too many uploads at once — wait a minute and add the rest.');
                                break;
                            }
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                const detail = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Upload failed.');
                                this.errors.push(`${file.name}: ${detail}`);
                                continue;
                            }
                            added++;
                        } catch (e) {
                            this.errors.push(`${file.name}: the upload could not be completed.`);
                        }
                    }

                    this.uploading = false;
                    this.progressLabel = '';
                    if (added > 0) {
                        window.location.reload();
                    }
                },
            };
        };
    </script>
@endonce
