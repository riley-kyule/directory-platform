<li>
    @if ($node->activeChildren->isNotEmpty())
        <details>
            <summary class="flex min-h-11 cursor-pointer items-center rounded-md px-2 py-2 font-medium text-stone-700 marker:text-stone-500 hover:bg-stone-100">
                {{ $node->name }}
            </summary>
            <ul class="mt-1 space-y-1 border-l border-stone-200 pl-4">
                <li><a href="{{ url($node->publicPath()) }}" class="flex min-h-11 items-center rounded-md px-2 py-2 font-semibold text-rose-700 hover:bg-stone-100">View all in {{ $node->name }}</a></li>
                @foreach ($node->activeChildren as $child)
                    @include('directory.partials.location-sidebar-node', ['node' => $child])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ url($node->publicPath()) }}" class="flex min-h-11 items-center rounded-md px-2 py-2 text-stone-700 hover:bg-stone-100 hover:text-rose-700">{{ $node->name }}</a>
    @endif
</li>
