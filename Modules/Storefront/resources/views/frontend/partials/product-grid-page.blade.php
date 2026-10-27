{{-- Cards only: the first render of a grid and every infinite-scroll page (?partial=grid). --}}
@foreach ($cards as $card)
    <div data-product-id="{{ $card['id'] }}" class="flex">
        <x-theme::product-card :product="$card" :eager="($eager ?? false) && $loop->index < 4" class="w-full" />
    </div>
@endforeach
