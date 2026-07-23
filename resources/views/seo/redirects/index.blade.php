<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Redirects and profile slugs</h2></x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Create redirect or removal</h3>
                    <p class="mt-1 text-sm text-gray-600">Paths must be local, lowercase URLs without a domain or query string. Use 410 only when there is no equivalent replacement.</p>
                    <form method="POST" action="{{ route('seo.redirects.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                        <div><x-input-label for="source_path" value="Old source path" /><x-text-input id="source_path" name="source_path" class="mt-1 block w-full" :value="old('source_path')" placeholder="/old-page" required /></div>
                        <div><x-input-label for="target_path" value="Replacement path" /><x-text-input id="target_path" name="target_path" class="mt-1 block w-full" :value="old('target_path')" placeholder="/new-page" /></div>
                        <div><x-input-label for="status_code" value="Response" /><select id="status_code" name="status_code" class="mt-1 block w-full rounded-md border-gray-300" required><option value="301">301 · Permanent redirect</option><option value="302">302 · Temporary redirect</option><option value="307">307 · Temporary, preserve method</option><option value="308">308 · Permanent, preserve method</option><option value="410">410 · Permanently gone</option></select></div>
                        <div><x-input-label for="redirect_reason" value="Required reason" /><x-text-input id="redirect_reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required /></div>
                        <div class="sm:col-span-2"><x-primary-button>Create rule</x-primary-button></div>
                    </form>
                </section>

                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Change a profile slug</h3>
                    <p class="mt-1 text-sm text-gray-600">This is an explicit SEO action. The old profile URL is retained as a permanent, chain-free redirect.</p>
                    <form method="POST" class="mt-6 space-y-4" x-data="{ profileId: '{{ old('profile_id') }}' }" x-bind:action="'{{ url('/seo/profiles') }}/' + profileId + '/slug'">@csrf @method('PATCH')
                        <div><x-input-label for="profile_id" value="Profile" /><select id="profile_id" name="profile_id" x-model="profileId" class="mt-1 block w-full rounded-md border-gray-300" required><option value="">Choose profile</option>@foreach($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->display_name }} · /escort/{{ $profile->slug }}</option>@endforeach</select></div>
                        <div><x-input-label for="slug" value="New slug" /><x-text-input id="slug" name="slug" class="mt-1 block w-full" :value="old('slug')" placeholder="jane-new-slug" required /></div>
                        <div><x-input-label for="slug_reason" value="Required reason" /><x-text-input id="slug_reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required /></div>
                        <x-primary-button>Change slug and redirect old URL</x-primary-button>
                    </form>
                </section>
            </div>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6"><h3 class="text-lg font-semibold">Redirect rules</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Source</th><th class="px-5 py-3">Response</th><th class="px-5 py-3">Target</th><th class="px-5 py-3">Reason</th><th class="px-5 py-3">State</th></tr></thead>
                        <tbody class="divide-y divide-gray-200">@forelse($redirects as $redirect)<tr><td class="px-5 py-4 font-mono">{{ $redirect->source_path }}</td><td class="px-5 py-4">{{ $redirect->status_code }}</td><td class="px-5 py-4 font-mono">{{ $redirect->target_path ?? '—' }}</td><td class="max-w-xs px-5 py-4 text-gray-600">{{ $redirect->reason }}</td><td class="px-5 py-4"><form method="POST" action="{{ route('seo.redirects.toggle', $redirect) }}" class="space-y-2">@csrf @method('PATCH')<input name="reason" required minlength="5" class="w-44 rounded-md border-gray-300 text-xs" placeholder="Reason for state change"><button class="block font-semibold {{ $redirect->is_active ? 'text-red-600' : 'text-green-700' }}">{{ $redirect->is_active ? 'Deactivate' : 'Activate' }}</button></form></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No redirect rules.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                @if($redirects->hasPages())<div class="border-t p-5">{{ $redirects->links() }}</div>@endif
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6"><h3 class="text-lg font-semibold">Recent profile slug history</h3></div>
                <div class="divide-y">@forelse($slugHistory as $change)<div class="grid gap-2 p-4 text-sm sm:grid-cols-4"><span class="font-semibold">{{ $change->profile?->display_name ?? 'Deleted profile' }}</span><span class="font-mono text-gray-600">{{ $change->old_slug }}</span><span class="font-mono text-gray-600">{{ $change->new_slug }}</span><span class="text-gray-500">{{ $change->changed_at->format('j M Y H:i') }} · {{ $change->changedBy?->email ?? 'System' }}</span></div>@empty<p class="p-6 text-sm text-gray-500">No profile slug changes.</p>@endforelse</div>
            </section>
        </div>
    </div>
</x-app-layout>
