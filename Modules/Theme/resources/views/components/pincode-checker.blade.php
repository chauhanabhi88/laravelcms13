@props(['endpoint' => null, 'productId' => null])
{{-- Shell in M01: the serviceability endpoint arrives with M07/M13 ({ serviceable, cod, message }). --}}
<form x-data="pincodeChecker({ endpoint: @js($endpoint), productId: @js($productId), messages: @js(trans('theme::frontend.pincode')) })"
    x-on:submit.prevent="check()" x-id="['pincode']" novalidate {{ $attributes }}>
    <label x-bind:for="$id('pincode')" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-ink">
        <x-theme::icon name="map-pin" size="size-4" class="text-muted" />
        {{ trans('theme::frontend.pincode.label') }}
    </label>
    <div class="flex max-w-sm gap-2">
        <input type="text" x-bind:id="$id('pincode')" x-model="pincode" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" autocomplete="postal-code"
            placeholder="{{ trans('theme::frontend.pincode.placeholder') }}" x-bind:aria-invalid="error ? 'true' : null"
            class="tabular block h-10 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
        <x-theme::button variant="secondary" loading="loading">{{ trans('theme::frontend.pincode.check') }}</x-theme::button>
    </div>
    <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-sm text-danger" role="alert"></p>
    <p x-show="result" x-cloak aria-live="polite" class="mt-1.5 text-sm"
        x-bind:class="result?.serviceable ? 'text-success' : 'text-danger'" x-text="result?.message"></p>
</form>
