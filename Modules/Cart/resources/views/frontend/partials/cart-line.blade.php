{{--
    One cart line: image, name, size/colour, price, quantity and actions, with revalidation
    notices. Forms carry data-cart-action so cartPage() can send them with fetch.
--}}
@php
    $blocked = $line->hasBlockingIssue();
    $notices = array_filter($line->issues, fn ($issue) => ! $issue->blocking);
    $blockingIssue = collect($line->issues)->first(fn ($issue) => $issue->blocking);
    $editable = ! $isBuyNow;
@endphp
<li class="flex gap-3 p-4 sm:gap-4" data-cart-line="{{ $line->itemId }}">
    <a @if ($line->productUrl) href="{{ $line->productUrl }}" @endif class="block w-20 shrink-0 sm:w-24" tabindex="-1" aria-hidden="true">
        <div @class(['aspect-[3/4] overflow-hidden rounded-lg bg-surface-alt', 'opacity-50 grayscale' => $blocked])>
            @if ($line->imageUrl)
                <img src="{{ $line->imageUrl }}" alt="" loading="lazy" class="size-full object-cover">
            @else
                <span class="flex size-full items-center justify-center text-muted"><x-theme::icon name="image" /></span>
            @endif
        </div>
    </a>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($line->productUrl)
                    <a href="{{ $line->productUrl }}" class="line-clamp-2 text-sm font-medium text-ink hover:text-accent">{{ $line->name }}</a>
                @else
                    <p class="line-clamp-2 text-sm font-medium text-ink">{{ $line->name }}</p>
                @endif
                @if ($line->variantLabel !== '')
                    <p class="mt-0.5 text-xs text-muted">{{ $line->variantLabel }}</p>
                @endif
            </div>
            @if (! $line->savedForLater && ! $blocked)
                <p class="tabular shrink-0 text-right text-sm font-bold text-ink">
                    <span class="sr-only">{{ trans('cart::front.line.line_total') }}</span>{{ $line->lineTotal()->format() }}
                </p>
            @endif
        </div>

        <x-theme::price :price="$line->unitPrice" :mrp="$line->unitMrp" size="sm" />

        @if ($blockingIssue)
            <x-theme::chip variant="danger" icon="alert" class="self-start">{{ $blockingIssue->message }}</x-theme::chip>
        @endif

        @foreach ($notices as $issue)
            <p @class([
                'flex items-start gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium',
                'bg-success-soft text-success' => $issue->type === 'price_changed' && $issue->newPrice?->compare($issue->oldPrice) < 0,
                'bg-warning-soft text-ink-soft' => ! ($issue->type === 'price_changed' && $issue->newPrice?->compare($issue->oldPrice) < 0),
            ]) role="status">
                <x-theme::icon name="info" size="size-3.5" class="mt-px shrink-0" /> {{ $issue->message }}
            </p>
        @endforeach

        <div class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-2">
            @if (! $line->savedForLater && ! $blocked)
                @if ($editable)
                    <form method="POST" action="{{ frontRoute('cart.update', [$line->itemId]) }}" data-cart-action data-cart-qty class="mr-2">
                        @csrf
                        @method('PATCH')
                        <x-theme::qty-stepper name="quantity" :id="'cart-qty-'.$line->itemId" :value="$line->quantity" :max="$line->maxQuantity" class="h-9" />
                        <noscript><button type="submit" class="ml-2 text-xs font-medium text-accent">{{ trans('cart::front.line.update') }}</button></noscript>
                    </form>
                @else
                    <span class="mr-2 text-sm text-ink-soft">{{ trans('cart::front.line.quantity') }}: <span class="tabular font-semibold text-ink">{{ $line->quantity }}</span></span>
                @endif
            @endif

            @if ($editable)
                @if ($line->savedForLater)
                    @if (! $blocked)
                        <form method="POST" action="{{ frontRoute('cart.move_to_cart', [$line->itemId]) }}" data-cart-action>
                            @csrf
                            <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-accent hover:bg-accent-soft">
                                <x-theme::icon name="cart" size="size-4" /> {{ trans('cart::front.line.move_to_cart') }}
                            </button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ frontRoute('cart.save_for_later', [$line->itemId]) }}" data-cart-action>
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-ink-soft hover:bg-surface-alt hover:text-ink">
                            <x-theme::icon name="clock" size="size-4" /> {{ trans('cart::front.line.save_for_later') }}
                        </button>
                    </form>
                @endif

                @if ($canUseWishlist && $line->productUrl)
                    <form method="POST" action="{{ frontRoute('cart.move_to_wishlist', [$line->itemId]) }}" data-cart-action>
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-ink-soft hover:bg-surface-alt hover:text-ink">
                            <x-theme::icon name="heart" size="size-4" /> {{ trans('cart::front.line.move_to_wishlist') }}
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ frontRoute('cart.remove', [$line->itemId]) }}" data-cart-action>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-ink-soft hover:bg-danger-soft hover:text-danger">
                        <x-theme::icon name="trash" size="size-4" /> {{ trans('cart::front.line.remove') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</li>
