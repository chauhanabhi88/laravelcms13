@extends('theme::layouts.seller.master')

@section('title', trans($coupon ? 'promotion::seller.edit_title' : 'promotion::seller.create_title'))

@section('content')
    {{-- Supplier coupon form: percent or flat, on all of the seller's products or chosen ones. $coupon: null on create. --}}
    @php
        $selectedProducts = array_map('intval', (array) old('product_ids', $coupon?->scope === 'product' ? $coupon->scopeIdList() : []));
        $scope = (string) old('scope', $coupon?->scope ?? 'seller');
    @endphp
    <form method="POST" action="{{ $coupon ? frontRoute('seller.coupon.update', [$coupon->id]) : frontRoute('seller.coupon.store') }}"
        class="max-w-3xl space-y-4 rounded-card border border-line bg-surface p-4 shadow-card sm:p-6" x-data="{ scope: @js($scope) }">
        @csrf
        @if ($coupon)
            @method('PUT')
        @endif

        <p class="text-sm text-muted">{{ trans('promotion::seller.hint') }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-theme::input name="code" :label="trans('promotion::coupon.fields.code')" :value="$coupon?->code" required maxlength="30" class="uppercase" />
            <x-theme::input name="name" :label="trans('promotion::coupon.fields.name')" :value="$coupon?->name" required maxlength="100" />
        </div>
        <x-theme::input name="description" :label="trans('promotion::coupon.fields.description')" :value="$coupon?->description" maxlength="255" />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-theme::select name="type" :label="trans('promotion::coupon.fields.type')" :value="$coupon?->type ?? 'percent'" required
                :options="['percent' => trans('promotion::promotion.options.types.percent'), 'flat' => trans('promotion::promotion.options.types.flat')]" />
            <x-theme::input name="value" type="number" step="0.01" min="0" :label="trans('promotion::coupon.fields.value')" :value="$coupon?->value?->toDecimal()" required />
            <x-theme::input name="max_discount" type="number" step="0.01" min="0" :label="trans('promotion::coupon.fields.max_discount')" :value="$coupon?->max_discount?->toDecimal()" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-theme::input name="min_cart_value" type="number" step="0.01" min="0" :label="trans('promotion::coupon.fields.min_cart_value')" :value="$coupon?->min_cart_value?->toDecimal() ?? '0'" />
            <x-theme::input name="usage_limit_total" type="number" min="1" :label="trans('promotion::coupon.fields.usage_limit_total')" :value="$coupon?->usage_limit_total" />
            <x-theme::input name="usage_limit_per_customer" type="number" min="1" :label="trans('promotion::coupon.fields.usage_limit_per_customer')" :value="$coupon !== null ? $coupon->usage_limit_per_customer : 1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-theme::input name="starts_at" type="datetime-local" :label="trans('promotion::coupon.fields.starts_at')" :value="$coupon?->starts_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')" required />
            <x-theme::input name="ends_at" type="datetime-local" :label="trans('promotion::coupon.fields.ends_at')" :value="$coupon?->ends_at?->format('Y-m-d\TH:i') ?? now()->addWeek()->format('Y-m-d\TH:i')" required />
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-ink">{{ trans('promotion::coupon.fields.scope') }}</legend>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="scope" value="seller" x-model="scope" @checked($scope === 'seller')> {{ trans('promotion::seller.scope.seller') }}</label>
                <label class="flex items-center gap-2"><input type="radio" name="scope" value="product" x-model="scope" @checked($scope === 'product')> {{ trans('promotion::seller.fields.products') }}</label>
            </div>
        </fieldset>

        <div x-show="scope === 'product'" @if ($scope !== 'product') style="display: none" @endif>
            <label for="product_ids" class="text-sm font-medium text-ink">{{ trans('promotion::seller.fields.products') }}</label>
            <select id="product_ids" name="product_ids[]" multiple size="8" class="mt-1 w-full rounded-lg border border-line-strong bg-surface p-2 text-sm">
                @foreach ($productOptions as $id => $label)
                    <option value="{{ $id }}" @selected(in_array((int) $id, $selectedProducts, true))>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-muted">{{ trans('promotion::seller.fields.products_hint') }}</p>
            <x-theme::field-error name="product_ids" />
        </div>

        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_visible" value="1" @checked((bool) old('is_visible', $coupon === null || $coupon->isVisible()))> {{ trans('promotion::coupon.fields.is_visible') }}</label>

        <div class="flex gap-3">
            <x-theme::button>{{ trans('promotion::seller.buttons.save') }}</x-theme::button>
            <x-theme::button variant="ghost" :href="frontRoute('seller.coupon.index')">{{ trans('promotion::seller.buttons.cancel') }}</x-theme::button>
        </div>
    </form>
@endsection
