@props(['disabled' => false])

@php
    $fieldName = $attributes->get('name');
    $hasError = $fieldName && $errors->has($fieldName);
@endphp

<input @disabled($disabled)
       @if($hasError) aria-invalid="true" aria-describedby="{{ str_replace(['.', '[', ']'], ['-', '-', ''], $fieldName) }}-error" @endif
       {{ $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>
