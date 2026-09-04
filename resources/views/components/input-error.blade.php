@props(['messages', 'id' => null])

@if ($messages)
    @php
        $field = collect($errors->messages())->search(fn (array $candidate) => $candidate === (array) $messages);
        $resolvedId = $id ?? ($field !== false ? str_replace(['.', '[', ']'], ['-', '-', ''], $field).'-error' : null);
    @endphp
    <ul @if($resolvedId) id="{{ $resolvedId }}" @endif role="alert" aria-live="polite" {{ $attributes->merge(['class' => 'text-sm font-medium text-red-700 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
