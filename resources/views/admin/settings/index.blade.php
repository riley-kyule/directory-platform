<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Directory operation</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">The change could not be saved.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Branding</h3>
                    <p class="mt-1 text-sm text-gray-600">Used across the admin panel and the public site. Leave a field blank to keep what's already set.</p>
                </div>
                <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 sm:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label value="Site logo" />
                        <div class="mt-2 flex items-center gap-4">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Current logo" class="h-12 w-auto">
                                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove</label>
                            @else
                                <span class="text-sm text-gray-500">Using the default mark.</span>
                            @endif
                        </div>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="mt-2 block w-full text-sm text-gray-700">
                        <p class="mt-1 text-xs text-gray-500">PNG, JPEG, or WebP up to 2 MB. Automatically fitted to a transparent 600×180 canvas.</p>
                        <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Favicon" />
                        <div class="mt-2 flex items-center gap-4">
                            @if ($faviconUrl)
                                <img src="{{ $faviconUrl }}" alt="Current favicon" class="h-8 w-8">
                                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="remove_favicon" value="1" class="rounded border-gray-300"> Remove</label>
                            @else
                                <span class="text-sm text-gray-500">Using the default icon.</span>
                            @endif
                        </div>
                        <input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp" class="mt-2 block w-full text-sm text-gray-700">
                        <p class="mt-1 text-xs text-gray-500">PNG, JPEG, or WebP up to 512 KB. Automatically fitted to a transparent 512×512 square.</p>
                        <x-input-error :messages="$errors->get('favicon')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end"><x-primary-button>Save branding</x-primary-button></div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Directory operation</h3>
                    <p class="mt-1 text-sm text-gray-600">These values take effect without a deployment. The server upload ceiling remains 50 MB.</p>
                </div>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    @method('PATCH')
                    <div class="sm:col-span-2 lg:col-span-3">
                        <h4 class="text-sm font-semibold text-gray-900">Site identity</h4>
                        <p class="mt-1 text-sm text-gray-600">The website title feeds the header, page titles, structured data, and the @{{platform_name}} token in legal policies. Leave blank to fall back to the APP_NAME environment value.</p>
                    </div>
                    <div>
                        <x-input-label for="platform_name" value="Website title" />
                        <x-text-input id="platform_name" name="platform_name" maxlength="80" class="mt-1 block w-full" :value="old('platform_name', $settings['platform_name'])" placeholder="{{ config('app.name') }}" />
                        <x-input-error :messages="$errors->get('platform_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="support_email" value="Support / contact email" />
                        <x-text-input id="support_email" name="support_email" type="email" class="mt-1 block w-full" :value="old('support_email', $settings['support_email'])" placeholder="support@yourdomain.com" />
                        <p class="mt-1 text-xs text-gray-500">Feeds the @{{support_email}} token in legal policies. Set a real, monitored address before launch.</p>
                        <x-input-error :messages="$errors->get('support_email')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3 border-t border-gray-200 pt-5">
                        <h4 class="text-sm font-semibold text-gray-900">Search-engine ownership</h4>
                        <p class="mt-1 text-sm text-gray-600">Paste only the verification token from each provider's HTML meta-tag method. The tags appear on every public page immediately after saving.</p>
                    </div>
                    <div>
                        <x-input-label for="google_site_verification" value="Google Search Console token" />
                        <x-text-input id="google_site_verification" name="google_site_verification" maxlength="255" class="mt-1 block w-full" :value="old('google_site_verification', $settings['google_site_verification'])" autocomplete="off" />
                        <p class="mt-1 text-xs text-gray-500">From <code>content="…"</code> in the Google verification meta tag.</p>
                        <x-input-error :messages="$errors->get('google_site_verification')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="bing_site_verification" value="Bing Webmaster Tools token" />
                        <x-text-input id="bing_site_verification" name="bing_site_verification" maxlength="255" class="mt-1 block w-full" :value="old('bing_site_verification', $settings['bing_site_verification'])" autocomplete="off" />
                        <p class="mt-1 text-xs text-gray-500">From <code>content="…"</code> in the msvalidate.01 meta tag.</p>
                        <x-input-error :messages="$errors->get('bing_site_verification')" class="mt-2" />
                    </div>
                    <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                        <input type="checkbox" name="age_gate_enabled" value="1" @checked(old('age_gate_enabled', $settings['age_gate_enabled'])) class="mt-1 rounded border-gray-300 text-indigo-600">
                        <span>
                            <strong class="block text-sm text-gray-900">Require an age (18+) consent gate</strong>
                            <span class="mt-1 block text-sm text-gray-600">Optional. When enabled, first-time visitors must confirm they're 18+ before seeing any listing content. Search engines and policy pages are never gated.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:col-span-2 lg:col-span-3">
                        <input type="checkbox" name="privileged_mfa_enforced" value="1" @checked(old('privileged_mfa_enforced', $settings['privileged_mfa_enforced'])) class="mt-1 rounded border-gray-300 text-indigo-600">
                        <span>
                            <strong class="block text-sm text-gray-900">Require authenticator MFA for privileged roles</strong>
                            <span class="mt-1 block text-sm text-gray-600">Optional. When enabled, Admin, CSR, and SEO accounts must enroll and complete an authenticator challenge. Leave disabled when privileged authentication is handled through an approved SSO provider.</span>
                        </span>
                    </label>
                    @foreach ([
                        ['agency_profile_limit', 'Agency profile limit', 1, 100, 1],
                        ['new_profile_days', 'New profile window (days)', 1, 365, 1],
                        ['listing_rotation_hours', 'Listing rotation interval (hours)', 1, 168, 1],
                        ['micro_location_min_profiles', 'Micro-location index threshold', 2, 100, 1],
                        ['maximum_file_megabytes', 'Maximum image size (MB)', 1, 50, 1],
                        ['minimum_width', 'Minimum image width (px)', 200, 5000, 1],
                        ['minimum_height', 'Minimum image height (px)', 200, 5000, 1],
                        ['maximum_dimension', 'Maximum image dimension (px)', 600, 20000, 1],
                        ['maximum_megapixels', 'Maximum decoded megapixels', 1, 100, 1],
                        ['minimum_aspect_ratio', 'Minimum aspect ratio', 0.1, 5, 0.1],
                        ['maximum_aspect_ratio', 'Maximum aspect ratio', 0.1, 5, 0.1],
                        ['webp_quality', 'WebP quality', 50, 100, 1],
                        ['processing_memory_limit_mb', 'Image processing memory ceiling (MB)', 128, 4096, 1],
                        ['video_max_megabytes', 'Maximum video size (MB)', 1, 2048, 1],
                        ['video_max_duration_seconds', 'Maximum video duration (seconds)', 5, 1800, 1],
                    ] as [$field, $label, $min, $max, $step])
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" type="number" :min="$min" :max="$max" :step="$step" class="mt-1 block w-full" :value="old($field, $settings[$field])" required />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                    @foreach ([
                        ['ffmpeg_path', 'ffmpeg path', '/usr/bin/ffmpeg'],
                        ['ffprobe_path', 'ffprobe path', '/usr/bin/ffprobe'],
                    ] as [$field, $label, $placeholder])
                        <div class="sm:col-span-2 lg:col-span-3">
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" type="text" class="mt-1 block w-full font-mono" :value="old($field, $settings[$field])" :placeholder="$placeholder" />
                            <p class="mt-1 text-sm text-gray-600">Absolute path to the executable on this server &mdash; find it by running <code>which {{ str($field)->before('_') }}</code> over SSH. This value is run as a command. Leave both blank to disable video uploads (photos are unaffected).</p>
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                    <div class="flex items-end lg:col-span-3"><x-primary-button>Save operational settings</x-primary-button></div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
