{{-- One seller's package: "Sold by {store}" + its delivery estimate, then its lines. --}}
<section class="rounded-card border border-line bg-surface shadow-card" aria-label="{{ trans('cart::front.group.sold_by', ['store' => $group->sellerName]) }}">
    <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-line px-4 py-3">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-ink">
            <x-theme::icon name="store" size="size-4" class="text-muted" />
            @if ($group->sellerUrl)
                <a href="{{ $group->sellerUrl }}" class="hover:text-accent hover:underline">{{ trans('cart::front.group.sold_by', ['store' => $group->sellerName]) }}</a>
            @else
                {{ trans('cart::front.group.sold_by', ['store' => $group->sellerName]) }}
            @endif
        </h2>
        <p class="flex items-center gap-1.5 text-xs text-muted">
            <x-theme::icon name="truck" size="size-4" />
            @if ($group->eddText)
                <span class="font-medium text-ink-soft">{{ trans('cart::front.group.delivery_by', ['date' => $group->eddText]) }}</span>
            @else
                {{ trans('cart::front.group.add_pincode') }}
            @endif
        </p>
    </header>
    <ul class="divide-y divide-line">
        @foreach ($group->lines as $line)
            @include('cart::frontend.partials.cart-line', ['line' => $line])
        @endforeach
    </ul>
</section>
