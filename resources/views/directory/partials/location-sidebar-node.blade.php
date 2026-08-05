<li>
    @if ($node->activeChildren->isNotEmpty())
        <details>
            <summary class="cursor-pointer rounded-md px-2 py-1.5 marker:text-stone-400 hover:bg-stone-100">
                <a href="{{ url($node->publicPath()) }}" class="font-medium text-stone-700 hover:text-rose-600">{{ $node->name }}</a>
            </summary>
            <ul class="mt-1 space-y-1 border-l border-stone-200 pl-4">
                @foreach ($node->activeChildren as $child)
                    @include('directory.partials.location-sidebar-node', ['node' => $child])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ url($node->publicPath()) }}" class="block rounded-md px-2 py-1.5 text-stone-600 hover:bg-stone-100 hover:text-rose-600">{{ $node->name }}</a>
    @endif
</li>
