@php $faviconUrl = app(\App\Services\DirectorySettings::class)->faviconUrl(); @endphp
<link rel="icon" href="{{ $faviconUrl ?? asset('favicon.ico') }}">
