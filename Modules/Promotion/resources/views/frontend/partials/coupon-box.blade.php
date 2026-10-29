{{--
    Coupon box of the cart page and checkout (plan M18 §5-6): enter / remove a code, the applied
    coupon or automatic offer and the "Available offers" list. Plain form posts (redirect back with
    a flash message), so it works the same on every page without JavaScript.
    Needs $snapshot (CartSnapshot) and $totals (Totals); $couponContext = cart | checkout;
    $paymentMethod in checkout.
--}}
@php
    $couponService = app(\Modules\Promotion\Services\CouponService::class);
    $paymentMethod ??= null;
    $couponContext ??= 'cart';
    $couponStatus = $couponService->status($snapshot, $totals, $paymentMethod);
    $couponOffers = $couponService->offers($snapshot, $paymentMethod);
    $cartTypeField = $snapshot->type;
@endphp
<section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" aria-labelledby="coupon-box-heading" data-coupon-box="{{ $couponContext }}">
    <h2 id="coupon-box-heading" class="flex items-center gap-1.5 text-sm font-semibold tracking-wide text-muted uppercase">
        <x-theme::icon name="tag" size="size-4" /> {{ trans('promotion::front.box.title') }}
    </h2>

    @if (session('coupon_success'))
        <p class="mt-3 rounded-lg bg-success-soft px-3 py-2 text-sm font-medium text-success" role="status">{{ session('coupon_success') }}</p>
    @endif
    @if (session('coupon_error'))
        <p class="mt-3 rounded-lg bg-danger/10 px-3 py-2 text-sm font-medium text-danger" role="alert" data-coupon-error>{{ session('coupon_error') }}</p>
    @endif

    @if ($couponStatus['applied'])
        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-dashed border-success/50 bg-success-soft px-3 py-2" data-coupon-applied="{{ $couponStatus['code'] }}">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-success">{{ trans('promotion::front.box.applied', ['code' => $couponStatus['code']]) }}</p>
                <p class="text-xs text-success">{{ trans('promotion::front.box.you_save', ['amount' => $couponStatus['saving']->format()]) }}</p>
            </div>
            <form method="POST" action="{{ frontRoute('cart.coupon.remove') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="cart_type" value="{{ $cartTypeField }}">
                <button type="submit" class="text-sm font-medium text-danger hover:underline">{{ trans('promotion::front.box.remove') }}</button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ frontRoute('cart.coupon.apply') }}" class="mt-3 flex gap-2">
            @csrf
            <input type="hidden" name="cart_type" value="{{ $cartTypeField }}">
            @if ($paymentMethod)
                <input type="hidden" name="payment_method" value="{{ $paymentMethod }}">
            @endif
            <label for="coupon-code-{{ $couponContext }}" class="sr-only">{{ trans('promotion::front.box.label') }}</label>
            <input type="text" name="code" id="coupon-code-{{ $couponContext }}" maxlength="30" autocomplete="off" required
                value="{{ old('code', $couponStatus['code']) }}" placeholder="{{ trans('promotion::front.box.placeholder') }}"
                class="h-10 min-w-0 flex-1 rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink uppercase placeholder:normal-case focus:border-accent focus:outline-none">
            <x-theme::button variant="secondary">{{ trans('promotion::front.box.apply') }}</x-theme::button>
        </form>
        @if ($couponStatus['code'] && $couponStatus['message'])
            <div class="mt-2 flex items-start justify-between gap-3">
                <p class="flex items-start gap-1.5 text-xs text-danger" data-coupon-not-applied><x-theme::icon name="alert" size="size-4" class="shrink-0" /> {{ $couponStatus['message'] }}</p>
                <form method="POST" action="{{ frontRoute('cart.coupon.remove') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="cart_type" value="{{ $cartTypeField }}">
                    <button type="submit" class="shrink-0 text-xs font-medium text-muted hover:underline">{{ trans('promotion::front.box.remove') }}</button>
                </form>
            </div>
        @endif
    @endif

    @if ($couponStatus['offer'])
        <p class="mt-3 flex items-center gap-1.5 text-sm font-medium text-success" data-offer-applied>
            <x-theme::icon name="check-circle" size="size-4" />
            {{ trans('promotion::front.box.offer_applied', ['name' => $couponStatus['offer']['name']]) }} · {{ $couponStatus['offer']['saving']->format() }}
        </p>
    @endif

    <details class="group mt-3" @if (session('coupon_error')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-accent hover:underline">
            {{ trans('promotion::front.box.view_offers') }}
            <x-theme::icon name="chevron-down" size="size-4" class="transition group-open:rotate-180" />
        </summary>
        @if ($couponOffers === [])
            <p class="mt-3 text-sm text-muted">{{ trans('promotion::front.box.no_offers') }}</p>
        @else
            <ul class="mt-3 space-y-2" aria-label="{{ trans('promotion::front.box.offers_title') }}">
                @foreach ($couponOffers as $row)
                    @php
                        $offerCoupon = $row['coupon'];
                        $offerEvaluation = $row['evaluation'];
                        $isCurrent = $snapshot->couponCode === $offerCoupon->code;
                    @endphp
                    <li class="rounded-lg border border-line p-3" data-offer="{{ $offerCoupon->code }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink"><span class="rounded-chip border border-dashed border-accent px-1.5 py-0.5 text-xs tracking-wide text-accent">{{ $offerCoupon->code }}</span> {{ $offerCoupon->benefitText() }}</p>
                                @if ($offerCoupon->description)
                                    <p class="mt-1 text-xs text-ink-soft">{{ $offerCoupon->description }}</p>
                                @endif
                                <p class="mt-1 text-xs text-muted">
                                    @if ($offerCoupon->min_cart_value->isPositive())
                                        {{ trans('promotion::front.box.min_cart', ['amount' => $offerCoupon->min_cart_value->format()]) }} ·
                                    @endif
                                    @if ($offerCoupon->ends_at)
                                        {{ trans('promotion::front.box.valid_till', ['date' => $offerCoupon->ends_at->format('d M Y')]) }}
                                    @endif
                                </p>
                                @if (! $offerEvaluation->valid && $offerEvaluation->message)
                                    <p class="mt-1 text-xs text-deal">{{ $offerEvaluation->message }}</p>
                                @endif
                            </div>
                            @if ($isCurrent && $couponStatus['applied'])
                                <span class="shrink-0 text-xs font-semibold text-success">{{ trans('promotion::front.box.in_use') }}</span>
                            @elseif ($offerEvaluation->valid)
                                <form method="POST" action="{{ frontRoute('cart.coupon.apply') }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="code" value="{{ $offerCoupon->code }}">
                                    <input type="hidden" name="cart_type" value="{{ $cartTypeField }}">
                                    @if ($paymentMethod)
                                        <input type="hidden" name="payment_method" value="{{ $paymentMethod }}">
                                    @endif
                                    <button type="submit" class="text-sm font-semibold text-accent hover:underline">{{ trans('promotion::front.box.use') }}</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @guest('customer')
            <p class="mt-3 text-xs text-muted">{{ trans('promotion::front.box.login_hint') }}</p>
        @endguest
    </details>
</section>
