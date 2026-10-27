@props(['endpoint' => null, 'productId' => null, 'variantId' => null, 'method' => 'get', 'pincode' => null])
{{--
    Delivery check. The endpoint answers { serviceable, cod, message } and optionally
    delivery_text / cod_text / return_text (M07 /delivery-check). method "post" sends
    { pincode, product_id, variant_id } as JSON; a prefilled pincode is checked on load.
    The product page announces variant changes with the "variant-changed" window event.
--}}
<form x-data="pincodeChecker({ endpoint: @js($endpoint), productId: @js($productId), variantId: @js($variantId), method: @js(strtolower($method)), pincode: @js((string) $pincode), messages: @js(trans('theme::frontend.pincode')) })"
    x-on:submit.prevent="check()" x-on:variant-changed.window="setVariant($event.detail?.id)" x-id="['pincode']" novalidate {{ $attributes }}>
    <label x-bind:for="$id('pincode')" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-ink">
        <x-theme::icon name="map-pin" size="size-4" class="text-muted" />
        {{ trans('theme::frontend.pincode.label') }}
    </label>
    <div class="flex max-w-sm gap-2">
        <input type="text" x-bind:id="$id('pincode')" x-model="pincode" value="{{ $pincode }}" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" autocomplete="postal-code"
            placeholder="{{ trans('theme::frontend.pincode.placeholder') }}" x-bind:aria-invalid="error ? 'true' : null"
            class="tabular block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
        <x-theme::button variant="secondary" loading="loading">{{ trans('theme::frontend.pincode.check') }}</x-theme::button>
    </div>
    <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-sm text-danger" role="alert"></p>
    <div x-show="result" x-cloak aria-live="polite" class="mt-2 space-y-1.5 text-sm">
        <p class="flex items-start gap-1.5" x-bind:class="result?.serviceable ? 'text-success' : 'text-danger'">
            <x-theme::icon name="check-circle" size="size-4" class="mt-0.5 shrink-0" x-show="result?.serviceable" />
            <x-theme::icon name="alert" size="size-4" class="mt-0.5 shrink-0" x-show="! result?.serviceable" />
            <span x-text="result?.delivery_text ?? result?.message"></span>
        </p>
        <p class="flex items-start gap-1.5" x-show="result?.cod_text" x-bind:class="result?.cod ? 'text-success' : 'text-muted'">
            <x-theme::icon name="banknote" size="size-4" class="mt-0.5 shrink-0" />
            <span x-text="result?.cod_text"></span>
        </p>
        <p class="flex items-start gap-1.5 text-ink-soft" x-show="result?.return_text">
            <x-theme::icon name="rotate-ccw" size="size-4" class="mt-0.5 shrink-0" />
            <span x-text="result?.return_text"></span>
        </p>
    </div>
</form>
