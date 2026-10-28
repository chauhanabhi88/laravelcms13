{{-- Mini-cart drawer content (FR-CRT-005): latest lines, subtotal, View cart / Checkout. Returned as "html" with view=drawer. --}}
@php
    $lines = array_reverse($snapshot->activeLines());
    $shown = array_slice($lines, 0, 3);
    $more = count($lines) - count($shown);
@endphp
@if ($snapshot->isEmpty())
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-surface-alt text-muted"><x-theme::icon name="cart" size="size-6" /></span>
        <p class="mt-4 text-sm text-muted">{{ trans('cart::front.mini.empty') }}</p>
    </div>
@else
    <ul class="divide-y divide-line">
        @foreach ($shown as $line)
            <li class="flex gap-3 p-4">
                <div class="aspect-[3/4] w-16 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                    @if ($line->imageUrl)
                        <img src="{{ $line->imageUrl }}" alt="" loading="lazy" class="size-full object-cover">
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-sm font-medium text-ink">{{ $line->name }}</p>
                    <p class="mt-0.5 text-xs text-muted">
                        @if ($line->variantLabel !== '')
                            {{ $line->variantLabel }} ·
                        @endif
                        {{ trans('cart::front.line.quantity') }} {{ $line->quantity }}
                    </p>
                    @if ($line->hasBlockingIssue())
                        <x-theme::chip variant="danger" class="mt-1">{{ $line->issues[0]->message }}</x-theme::chip>
                    @else
                        <x-theme::price :price="$line->unitPrice" :mrp="$line->unitMrp" size="sm" class="mt-1" />
                    @endif
                </div>
                <form method="POST" action="{{ frontRoute('cart.remove', [$line->itemId]) }}" data-cart-action>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="-mr-2 flex size-9 items-center justify-center rounded-lg text-muted hover:bg-danger-soft hover:text-danger"
                        aria-label="{{ trans('cart::front.line.remove') }}: {{ $line->name }}">
                        <x-theme::icon name="trash" size="size-4" />
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
    @if ($more > 0)
        <p class="px-4 pb-2 text-xs text-muted">{{ trans_choice('cart::front.mini.more', $more, ['count' => $more]) }}</p>
    @endif

    <div class="border-t border-line p-4">
        <div class="tabular flex items-center justify-between text-sm">
            <span class="text-ink-soft">{{ trans('cart::front.mini.subtotal') }}</span>
            <span class="text-base font-bold text-ink">{{ $totals->subtotal->format() }}</span>
        </div>
        @if ($totals->youSave()->isPositive())
            <p class="mt-1 text-xs font-medium text-success">{{ trans('cart::front.summary.you_save', ['amount' => $totals->youSave()->format()]) }}</p>
        @endif
        <div class="mt-4 grid gap-2 @if ($checkoutUrl && $snapshot->canCheckout()) grid-cols-2 @endif">
            <x-theme::button variant="secondary" :href="\Modules\Cart\Support\CartUrls::cart()">{{ trans('cart::front.mini.view_cart') }}</x-theme::button>
            @if ($checkoutUrl && $snapshot->canCheckout())
                <x-theme::button :href="$checkoutUrl">{{ trans('cart::front.mini.checkout') }}</x-theme::button>
            @endif
        </div>
    </div>
@endif
