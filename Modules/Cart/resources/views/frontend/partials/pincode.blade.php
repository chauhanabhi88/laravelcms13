{{-- Delivery pincode for the per-seller delivery dates. Posts to cart.pincode (fetch or plain form). --}}
<form method="POST" action="{{ frontRoute('cart.pincode') }}" data-cart-action x-data="{ editing: @js($snapshot->deliveryPincode === null) }"
    class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-card">
    @csrf
    <x-theme::icon name="map-pin" size="size-5" class="text-muted" />
    @if ($snapshot->deliveryPincode !== null)
        <p x-show="! editing" class="flex-1 text-sm text-ink-soft">
            {!! trans('cart::front.pincode.deliver_to', ['pincode' => '<span class="tabular font-semibold text-ink">'.e($snapshot->deliveryPincode).'</span>']) !!}
        </p>
        <button type="button" x-show="! editing" x-on:click="editing = true; $nextTick(() => $refs.pincode.focus())"
            class="text-sm font-semibold text-accent hover:underline">{{ trans('cart::front.pincode.change') }}</button>
    @endif
    <div x-show="editing" @if ($snapshot->deliveryPincode !== null) x-cloak @endif class="flex flex-1 items-center gap-2">
        <label for="cart-pincode" class="sr-only">{{ trans('cart::front.pincode.label') }}</label>
        <input x-ref="pincode" id="cart-pincode" name="pincode" type="text" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" required
            value="{{ $snapshot->deliveryPincode }}" placeholder="{{ trans('cart::front.pincode.placeholder') }}" autocomplete="postal-code"
            class="tabular h-10 w-36 rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:border-accent focus:outline-none">
        <x-theme::button variant="secondary" size="sm">{{ trans('cart::front.pincode.check') }}</x-theme::button>
    </div>
</form>
