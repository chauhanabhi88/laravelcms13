{{--
    Reseller block (FR-ORD-002): "Selling to a customer?" — the end customer's address becomes the
    shipping address and each line gets the price the customer pays (≥ selling price, ≤ MRP × cap),
    with the margin shown live. Fields are part of the address form.
--}}
@php
    $session = $summary->session;
    $end = $session->endCustomer ?? [];
    $field = fn (string $key) => old('end_customer.'.$key, $end[$key] ?? null);
    $enabled = (bool) old('reseller', $session->isResellerOrder());
    $jsLines = array_map(fn ($line) => [
        'id' => $line->variantId,
        'price' => $line->unitPrice->toDecimal(),
        'qty' => $line->quantity,
    ], $summary->snapshot->lines());
    $jsPrices = [];
    foreach ($summary->snapshot->lines() as $line) {
        $jsPrices[$line->variantId] = (string) old('prices.'.$line->variantId, isset($session->customerPrices[$line->variantId]) ? $session->customerPrices[$line->variantId] : $line->unitPrice->toDecimal());
    }
@endphp
<section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5"
    x-data="{
        on: @js($enabled),
        lines: @js($jsLines),
        prices: @js($jsPrices),
        margin() {
            return this.lines.reduce((sum, line) => {
                const price = parseFloat(this.prices[line.id]);
                return sum + (isNaN(price) || price <= parseFloat(line.price) ? 0 : (price - parseFloat(line.price)) * line.qty);
            }, 0);
        },
        rupees(value) { return '₹' + value.toLocaleString('en-IN', { maximumFractionDigits: 2 }); },
    }"
    aria-labelledby="reseller-heading" data-reseller-block>
    <label class="flex cursor-pointer items-start gap-3">
        <input type="checkbox" name="reseller" value="1" x-model="on" @checked($enabled) class="mt-1 size-4 rounded accent-accent">
        <span>
            <span id="reseller-heading" class="block text-base font-semibold text-ink">{{ trans('order::front.reseller.toggle') }}</span>
            <span class="mt-0.5 block text-sm text-muted">{{ trans('order::front.reseller.toggle_text') }}</span>
        </span>
    </label>
    @error('reseller')
        <p class="mt-2 text-sm text-danger">{{ $message }}</p>
    @enderror

    <fieldset class="mt-5 space-y-5" x-show="on" x-bind:disabled="! on" @disabled(! $enabled) @unless ($enabled) x-cloak @endunless>
        <h3 class="text-sm font-semibold text-ink">{{ trans('order::front.reseller.end_customer') }}</h3>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-theme::input name="end_customer[name]" id="end-customer-name" :value="$field('name')" :label="trans('order::front.reseller.fields.name')" required maxlength="100" />
            <x-theme::input name="end_customer[phone]" id="end-customer-phone" type="tel" :value="$field('phone') ? \Modules\Customer\Support\PhoneNumber::national($field('phone')) : null" :label="trans('order::front.reseller.fields.phone')" required maxlength="14" inputmode="numeric" />
        </div>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-theme::input name="end_customer[pincode]" id="end-customer-pincode" :value="$field('pincode')" :label="trans('order::front.reseller.fields.pincode')" required maxlength="6" inputmode="numeric" class="tabular" />
            <div class="sm:col-span-2">
                <x-theme::input name="end_customer[address_line1]" id="end-customer-line1" :value="$field('address_line1')" :label="trans('order::front.reseller.fields.address_line1')" required maxlength="200" />
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-theme::input name="end_customer[address_line2]" id="end-customer-line2" :value="$field('address_line2')" :label="trans('order::front.reseller.fields.address_line2')" maxlength="200" />
            <x-theme::input name="end_customer[landmark]" id="end-customer-landmark" :value="$field('landmark')" :label="trans('order::front.reseller.fields.landmark')" optional maxlength="120" />
        </div>

        <div>
            <h3 class="text-sm font-semibold text-ink">{{ trans('order::front.reseller.prices') }}</h3>
            <ul class="mt-3 divide-y divide-line rounded-lg border border-line">
                @foreach ($summary->snapshot->lines() as $line)
                    <li class="flex flex-wrap items-center gap-3 px-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $line->name }}</p>
                            <p class="text-xs text-muted">
                                {{ trans('order::front.orders.qty', ['count' => $line->quantity]) }} ·
                                {{ trans('order::front.reseller.price_hint', ['min' => $line->unitPrice->format(), 'max' => \Modules\Core\Support\Money::of($maxPrices[$line->variantId])->format()]) }}
                            </p>
                        </div>
                        <label class="sr-only" for="price-{{ $line->variantId }}">{{ trans('order::front.reseller.prices') }}: {{ $line->name }}</label>
                        <div class="flex h-10 w-32 items-center overflow-hidden rounded-lg border border-line-strong bg-surface focus-within:border-accent">
                            <span class="px-2 text-sm text-muted">₹</span>
                            <input type="number" step="0.01" min="{{ $line->unitPrice->toDecimal() }}" max="{{ $maxPrices[$line->variantId] }}"
                                id="price-{{ $line->variantId }}" name="prices[{{ $line->variantId }}]" x-model="prices[{{ $line->variantId }}]"
                                value="{{ $jsPrices[$line->variantId] }}" class="tabular w-full border-0 bg-transparent pr-2 text-sm text-ink focus:ring-0 focus:outline-none">
                        </div>
                        @error('prices.'.$line->variantId)
                            <p class="w-full text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 flex items-center justify-between rounded-lg bg-success-soft px-3 py-2 text-sm font-semibold text-success">
                <span>{{ trans('order::front.reseller.you_earn') }}</span>
                <span class="tabular" x-text="rupees(margin())">{{ $summary->resellerMargin->format() }}</span>
            </p>
        </div>
    </fieldset>
</section>
