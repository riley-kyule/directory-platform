@props([
    'profile',
    'canManage' => false,
    'photoLimit' => 0,
    'videoLimit' => 0,
    'requiredPolicies' => null,
    'heading' => 'Photos & videos',
])

@php
    $settings = app(\App\Services\DirectorySettings::class);
    $photoMaxKb = $settings->integer('media.maximum_file_kilobytes');
    $videoMaxKb = $settings->integer('media.video_max_kilobytes');
    $policies = $requiredPolicies ?? collect();
    $photos = $profile->images;
    $videos = $profile->videos;
    $photoUsed = $photos->whereNotIn('status', ['rejected', 'private'])->count();
    $videoUsed = $videos->whereNotIn('status', ['rejected', 'private'])->count();
    $badge = fn (string $status) => match ($status) {
        'approved' => 'bg-green-100 text-green-800',
        'pending_review' => 'bg-blue-100 text-blue-800',
        'processing', 'quarantined' => 'bg-amber-100 text-amber-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $label = fn (string $status) => match ($status) {
        'approved' => 'Live',
        'pending_review' => 'Goes live with the profile',
        'processing', 'quarantined' => 'Processing',
        'rejected' => 'Rejected',
        default => str($status)->replace('_', ' ')->title()->toString(),
    };
@endphp

<section {{ $attributes->merge(['class' => 'space-y-8 bg-white p-6 shadow-sm sm:rounded-lg']) }} x-data="mediaManager()">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ $heading }}</h3>
        <p class="mt-1 text-sm text-gray-600">
            Every file is scanned and re-encoded privately. On a live profile it goes public as soon as that finishes; on a draft it publishes when the profile is activated.
        </p>
    </div>

    @if ($errors->hasAny(['image', 'video', 'policy_acceptances']))
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->get('image') as $message)<li>{{ $message }}</li>@endforeach
                @foreach ($errors->get('video') as $message)<li>{{ $message }}</li>@endforeach
                @foreach ($errors->get('policy_acceptances') as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Photos --------------------------------------------------------------- --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="font-semibold text-gray-900">Photos</h4>
            <p class="text-sm text-gray-500">{{ $photoUsed }} of {{ $photoLimit }} slots used · JPEG, PNG or WebP · at least {{ $settings->integer('media.minimum_width') }}px on each side</p>
        </div>

        @if ($canManage && $photoUsed < $photoLimit)
            <form method="POST" enctype="multipart/form-data" action="{{ route('profiles.media.store', $profile) }}"
                  class="flex flex-wrap items-end gap-4 rounded-md border border-dashed border-gray-300 p-4"
                  @submit="if (! validateFile($refs.photo, {{ $photoMaxKb }})) $event.preventDefault()">
                @csrf
                <div class="min-w-0 flex-1">
                    <x-input-label for="photo-input" value="Add a photo" />
                    <input id="photo-input" x-ref="photo" name="image" type="file" required
                           accept="image/jpeg,image/png,image/webp"
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
                    <p x-show="photoError" x-text="photoError" class="mt-1 text-sm text-red-600" x-cloak></p>
                </div>
                <x-policy-acceptances :policies="$policies" class="w-full" />
                <x-primary-button>Upload photo</x-primary-button>
            </form>
        @elseif ($canManage)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">All photo slots for the current package are in use. Remove a photo to add another.</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($photos as $image)
                <article class="overflow-hidden rounded-lg border border-gray-200">
                    @if (in_array($image->status, ['pending_review', 'approved']) && isset($image->derivatives['card']))
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
                        @if ($canManage)
                            <div class="flex items-center gap-3">
                                @if (in_array($image->status, ['rejected', 'processing']))
                                    <form method="POST" action="{{ route('profiles.media.retry', [$profile, $image]) }}">
                                        @csrf
                                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Retry</button>
                                    </form>
                                @endif
                                @if ($image->status !== 'processing')
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
                    No photos yet. At least one processed photo is required before a profile can be submitted for review.
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

        @if ($canManage && $videoLimit > 0 && $videoUsed < $videoLimit)
            <form method="POST" enctype="multipart/form-data" action="{{ route('profiles.media.videos.store', $profile) }}"
                  class="flex flex-wrap items-end gap-4 rounded-md border border-dashed border-gray-300 p-4"
                  @submit="if (! validateFile($refs.video, videoMaxKb)) $event.preventDefault()">
                @csrf
                <div class="min-w-0 flex-1">
                    <x-input-label for="video-input" value="Add a video" />
                    <input id="video-input" x-ref="video" name="video" type="file" required
                           accept="video/mp4,video/webm,video/quicktime"
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
                    <p x-show="videoError" x-text="videoError" class="mt-1 text-sm text-red-600" x-cloak></p>
                </div>
                <x-policy-acceptances :policies="$policies" class="w-full" />
                <x-primary-button>Upload video</x-primary-button>
            </form>
        @elseif ($canManage && $videoLimit === 0)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">The current package does not include video slots.</p>
        @elseif ($canManage)
            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">All video slots for the current package are in use. Remove a video to add another.</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @forelse ($videos as $video)
                <article class="overflow-hidden rounded-lg border border-gray-200">
                    @if (in_array($video->status, ['pending_review', 'approved']))
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
                        @if ($canManage)
                            <div class="flex items-center gap-3">
                                @if (in_array($video->status, ['rejected', 'processing']))
                                    <form method="POST" action="{{ route('profiles.media.videos.retry', [$profile, $video]) }}">
                                        @csrf
                                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Retry</button>
                                    </form>
                                @endif
                                @if ($video->status !== 'processing')
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
        window.mediaManager = function () {
            return {
                photoError: '',
                videoError: '',
                videoMaxKb: {{ $videoMaxKb }},
                validateFile(input, maxKb) {
                    const key = input === this.$refs.photo ? 'photoError' : 'videoError';
                    this[key] = '';
                    const file = input.files && input.files[0];
                    if (!file) { this[key] = 'Choose a file first.'; return false; }
                    if (file.size > maxKb * 1024) {
                        this[key] = 'That file is ' + Math.ceil(file.size / 1048576) + ' MB — the limit is ' + Math.floor(maxKb / 1024) + ' MB.';
                        return false;
                    }
                    return true;
                },
            };
        };
    </script>
@endonce
