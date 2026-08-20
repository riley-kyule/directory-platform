<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">System health</h2></x-slot>
    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @php
                $statusCounts = collect($checks)->countBy('status');
                $statusStyles = [
                    'critical' => ['card' => 'border-red-200 bg-red-50', 'label' => 'text-red-800', 'value' => 'text-red-950'],
                    'warning' => ['card' => 'border-amber-200 bg-amber-50', 'label' => 'text-amber-800', 'value' => 'text-amber-950'],
                    'ok' => ['card' => 'border-green-200 bg-green-50', 'label' => 'text-green-800', 'value' => 'text-green-950'],
                ];
            @endphp
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($statusStyles as $status => $style)
                    <div class="rounded-lg border p-5 {{ $style['card'] }}"><p class="text-sm font-semibold uppercase tracking-wide {{ $style['label'] }}">{{ $status }}</p><p class="mt-1 text-3xl font-black {{ $style['value'] }}">{{ $statusCounts[$status] ?? 0 }}</p></div>
                @endforeach
            </div>
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b p-6"><h3 class="text-lg font-semibold">Operational checks</h3><p class="mt-1 text-sm text-gray-600">Detailed results are restricted to Admin accounts. The public readiness endpoint returns status only.</p></div>
                <div class="divide-y">@foreach($checks as $name => $check)<div class="grid gap-3 p-5 sm:grid-cols-[auto_1fr_auto] sm:items-center"><span class="h-3 w-3 rounded-full {{ $check['status'] === 'ok' ? 'bg-green-500' : ($check['status'] === 'warning' ? 'bg-amber-500' : 'bg-red-600') }}"></span><div><p class="font-semibold capitalize">{{ str($name)->replace('_', ' ') }}</p><p class="text-sm text-gray-600">{{ $check['message'] }}</p></div><div class="text-right"><span class="text-xs font-bold uppercase {{ $check['status'] === 'ok' ? 'text-green-700' : ($check['status'] === 'warning' ? 'text-amber-700' : 'text-red-700') }}">{{ $check['status'] }}</span>@if(isset($check['value']))<p class="text-xs text-gray-500">{{ $check['value'] }}</p>@endif</div></div>@endforeach</div>
            </section>
        </div>
    </div>
</x-app-layout>
