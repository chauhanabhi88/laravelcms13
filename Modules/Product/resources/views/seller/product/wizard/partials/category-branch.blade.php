{{-- Recursive browse tree: leaves are pickable, branches expand. Expects $nodes, $trail. --}}
@if ($nodes === [] && isset($root))
    <button type="button" class="block w-full rounded px-2 py-1 text-left text-sm text-accent hover:bg-accent-soft"
        x-on:click="pick({ id: {{ (int) $root['id'] }}, breadcrumb: @js(implode(' › ', $trail)) })">{{ $root['name'] }}</button>
@endif
@foreach ($nodes as $node)
    @php($path = [...$trail, $node['name']])
    @if ($node['children'] === [])
        <button type="button" class="block w-full rounded px-2 py-1 text-left text-sm text-ink-soft hover:bg-accent-soft hover:text-accent"
            x-on:click="pick({ id: {{ (int) $node['id'] }}, breadcrumb: @js(implode(' › ', $path)) })">{{ $node['name'] }}</button>
    @else
        <details class="pl-2">
            <summary class="cursor-pointer rounded px-2 py-1 text-sm font-medium text-ink hover:bg-surface-alt">{{ $node['name'] }}</summary>
            <div class="border-l border-line pl-2">
                @include('product::seller.product.wizard.partials.category-branch', ['nodes' => $node['children'], 'trail' => $path, 'root' => null])
            </div>
        </details>
    @endif
@endforeach
