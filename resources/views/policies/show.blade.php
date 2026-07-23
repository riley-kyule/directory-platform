<x-public-layout :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl" :robots="$robots">
    <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <header class="border-b border-stone-200 pb-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-rose-600">{{ $policy->label() }}</p>
            <h1 class="mt-2 text-4xl font-black tracking-tight text-stone-950">{{ $policy->title }}</h1>
            @if ($policy->summary)<p class="mt-4 text-lg text-stone-600">{{ $policy->summary }}</p>@endif
            <p class="mt-4 text-sm text-stone-500">Version {{ $policy->version }} · Effective {{ $policy->published_at->format('j F Y') }}</p>
        </header>
        <div class="prose prose-stone mt-10 max-w-none">
            {!! str($policy->content)->markdown(['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    </article>
</x-public-layout>
