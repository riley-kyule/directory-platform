<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('directory.profiles.show', $profile->slug) }}" class="text-sm font-bold text-rose-600">Back to {{ $profile->display_name }}</a>
        <h1 class="mt-4 text-3xl font-black tracking-tight">Report a concern</h1>
        <p class="mt-3 text-stone-600">Reports are confidential and visible only to authorized moderation staff. If someone is in immediate danger, contact the appropriate local emergency service.</p>

        @if ($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('directory.profiles.report.store', $profile) }}" class="mt-8 space-y-5 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">@csrf
            <div>
                <label for="category" class="text-sm font-bold text-stone-700">What is the concern?</label>
                <select id="category" name="category" required class="mt-1 block w-full rounded-xl border-stone-300">
                    <option value="">Choose a category</option>
                    @foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="details" class="text-sm font-bold text-stone-700">Details</label>
                <textarea id="details" name="details" rows="8" minlength="30" maxlength="5000" required class="mt-1 block w-full rounded-xl border-stone-300" placeholder="Explain what happened and include the relevant context.">{{ old('details') }}</textarea>
            </div>
            <div>
                <label for="email" class="text-sm font-bold text-stone-700">Email for confidential follow-up</label>
                <input id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required class="mt-1 block w-full rounded-xl border-stone-300">
            </div>
            <div class="hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <button class="w-full rounded-full bg-stone-950 px-6 py-3 text-sm font-bold text-white hover:bg-rose-600">Send confidential report</button>
        </form>
    </div>
</x-public-layout>
