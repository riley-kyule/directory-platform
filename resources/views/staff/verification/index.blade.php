<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Verification evidence</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))<div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif

            @if($selectedProfile)
                <section class="grid gap-6 bg-white p-6 shadow-sm sm:rounded-lg lg:grid-cols-[.8fr_1.2fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Selected profile</p>
                        <h3 class="mt-1 text-2xl font-bold">{{ $selectedProfile->display_name }}</h3>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div><dt class="text-gray-500">Current internal status</dt><dd class="font-semibold capitalize">{{ $selectedProfile->verification_status }}</dd></div>
                            <div><dt class="text-gray-500">Date of birth</dt><dd class="font-semibold">{{ $selectedProfile->date_of_birth->format('j M Y') }} · {{ $selectedProfile->date_of_birth->age }} years</dd></div>
                            <div><dt class="text-gray-500">Account / agency</dt><dd class="font-semibold">{{ $selectedProfile->owner?->email ?? $selectedProfile->currentAgency->first()?->name ?? 'Unassigned' }}</dd></div>
                        </dl>
                        <p class="mt-5 rounded-md bg-amber-50 p-3 text-xs text-amber-900">Evidence references and notes are encrypted and never displayed publicly. Do not paste document images or full identity numbers here.</p>
                    </div>
                    @can('verification.manage')
                        <form method="POST" action="{{ route('staff.verification.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                            <input type="hidden" name="profile_id" value="{{ $selectedProfile->id }}">
                            <label class="text-sm"><span class="font-medium">Check type</span><select name="check_type" required class="mt-1 block w-full rounded-md border-gray-300">@foreach($checkTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                            <label class="text-sm"><span class="font-medium">Result</span><select name="status" required class="mt-1 block w-full rounded-md border-gray-300"><option value="pending">Pending evidence</option><option value="verified">Verified</option><option value="rejected">Rejected</option></select></label>
                            <label class="text-sm"><span class="font-medium">Secure evidence reference</span><input name="evidence_reference" class="mt-1 block w-full rounded-md border-gray-300" placeholder="External vault or case reference"></label>
                            <label class="text-sm"><span class="font-medium">Expiry (optional)</span><input type="date" name="expires_at" min="{{ now()->addDay()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm sm:col-span-2"><span class="font-medium">Required staff notes</span><textarea name="notes" rows="5" required class="mt-1 block w-full rounded-md border-gray-300"></textarea></label>
                            <div class="sm:col-span-2"><x-primary-button>Record immutable check</x-primary-button></div>
                        </form>
                    @endcan
                </section>

                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b p-5"><h3 class="font-bold">Check history</h3></div>
                    <div class="divide-y">@forelse($selectedProfile->verificationChecks->sortByDesc('created_at') as $check)<div class="grid gap-3 p-5 text-sm sm:grid-cols-[1fr_1fr_2fr]"><div><p class="font-semibold">{{ $check->label() }}</p><p class="text-xs text-gray-500">{{ $check->created_at->format('j M Y H:i') }}</p></div><div><p class="font-semibold capitalize">{{ $check->status }}</p><p class="text-xs text-gray-500">{{ $check->performer?->email ?? 'Former staff account' }}@if($check->expires_at) · expires {{ $check->expires_at->format('j M Y') }}@endif</p></div><div><p><strong>Reference:</strong> {{ $check->evidence_reference ?? 'Pending' }}</p><p class="mt-1 text-gray-600">{{ $check->notes }}</p></div></div>@empty<p class="p-6 text-sm text-gray-500">No verification checks recorded.</p>@endforelse</div>
                </section>
            @endif

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-5"><h3 class="font-bold">Profiles</h3><p class="text-sm text-gray-600">Verification status is internal until public levels and display rules are formally defined.</p></div>
                <div class="divide-y">@foreach($profiles as $profile)<a href="{{ route('staff.verification.index', ['profile' => $profile->id]) }}" class="grid gap-2 p-4 hover:bg-gray-50 sm:grid-cols-[1fr_1fr_auto]"><span class="font-semibold">{{ $profile->display_name }}</span><span class="text-sm capitalize text-gray-600">{{ str($profile->status->value)->replace('_', ' ') }}</span><span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase">{{ $profile->verification_status }}</span></a>@endforeach</div>
                @if($profiles->hasPages())<div class="border-t p-5">{{ $profiles->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
