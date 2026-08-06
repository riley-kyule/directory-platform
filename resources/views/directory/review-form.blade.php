<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('directory.profiles.show', $profile->slug) }}" class="text-sm font-bold text-rose-600">Back to {{ $profile->display_name }}</a>
        <h1 class="mt-4 text-3xl font-black tracking-tight">Leave a review</h1>
        <p class="mt-3 text-stone-600">Reviews are checked before they're published. Your email is never shown publicly — it's used only to prevent spam.</p>

        @if ($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('directory.profiles.reviews.store', $profile) }}" class="mt-8 space-y-5 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <div>
                <label for="rating" class="text-sm font-bold text-stone-700">Rating</label>
                <select id="rating" name="rating" required class="mt-1 block w-full rounded-xl border-stone-300">
                    @foreach ([5, 4, 3, 2, 1] as $star)
                        <option value="{{ $star }}" @selected(old('rating') == $star)>{{ $star }} star{{ $star === 1 ? '' : 's' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reviewer_name" class="text-sm font-bold text-stone-700">Your name (optional)</label>
                <input id="reviewer_name" type="text" name="reviewer_name" maxlength="80" value="{{ old('reviewer_name') }}" class="mt-1 block w-full rounded-xl border-stone-300">
            </div>
            <div>
                <label for="email" class="text-sm font-bold text-stone-700">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required class="mt-1 block w-full rounded-xl border-stone-300">
            </div>
            <div>
                <label for="body" class="text-sm font-bold text-stone-700">Review</label>
                <textarea id="body" name="body" rows="6" minlength="20" maxlength="2000" required class="mt-1 block w-full rounded-xl border-stone-300" placeholder="Share your experience.">{{ old('body') }}</textarea>
            </div>
            <button class="w-full rounded-full bg-stone-950 px-6 py-3 text-sm font-bold text-white hover:bg-rose-600">Submit review</button>
        </form>
    </div>
</x-public-layout>
