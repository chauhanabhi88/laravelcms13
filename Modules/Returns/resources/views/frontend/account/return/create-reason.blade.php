@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.wizard.title'))
@section('heading', trans('returns::front.wizard.title'))

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
    $anyReturn = $items->contains(fn ($item) => $item->allows('return'));
    $anyExchange = $items->contains(fn ($item) => $item->allows('exchange'));
    $type = old('type', $draft->type ?? ($anyReturn ? 'return' : 'exchange'));
@endphp

@section('account')
    {{-- Step 1 (plan M15 §5.3): items, return or exchange (new size / colour) and the reason. --}}
    @include('returns::frontend.account.return.partials.wizard-header', ['current' => 1])

    <form method="POST" action="{{ frontRoute('customer.returns.store_step', ['step' => 'reason']) }}" class="space-y-5"
        x-data="{ type: @js($type), loading: false }" x-on:submit="loading = true" data-step="reason">
        @csrf

        <x-theme::card padding="p-5">
            <fieldset>
                <legend class="text-base font-semibold text-ink">{{ trans('returns::front.wizard.type') }}</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($types as $value => $label)
                        @php
                            $allowed = $value === 'return' ? $anyReturn : $anyExchange;
                        @endphp
                        <label @class(['flex cursor-pointer items-start gap-3 rounded-card border border-line p-3 has-checked:border-accent has-checked:bg-accent-soft', 'opacity-50 cursor-not-allowed' => ! $allowed])>
                            <input type="radio" name="type" value="{{ $value }}" x-model="type" class="mt-1 size-4 accent-accent" @disabled(! $allowed)>
                            <span>
                                <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                                <span class="block text-xs text-muted">{{ trans('returns::front.wizard.type_hint.'.$value) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-theme::field-error name="type" />
            </fieldset>
        </x-theme::card>

        <x-theme::card padding="p-0">
            <h2 class="border-b border-line px-5 py-3 text-base font-semibold text-ink">{{ trans('returns::front.wizard.items') }}</h2>
            <ul class="divide-y divide-line">
                @foreach ($items as $itemId => $candidate)
                    @php
                        $item = $candidate->item;
                        $line = $draft->items[$itemId] ?? null;
                        $selected = old('items.'.$itemId.'.selected', $line !== null || $items->count() === 1 ? '1' : null);
                    @endphp
                    <li class="flex flex-wrap gap-4 px-5 py-4" data-item="{{ $itemId }}">
                        <label class="flex min-w-0 flex-1 items-start gap-3">
                            <input type="checkbox" name="items[{{ $itemId }}][selected]" value="1" @checked($selected) class="mt-1 size-4 rounded accent-accent">
                            <span class="size-14 shrink-0 overflow-hidden rounded-lg bg-surface-alt">
                                @if ($image = \Modules\Storefront\Support\StorefrontUrls::productImage($item->image, 'thumb'))
                                    <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-ink">{{ $item->name }}</span>
                                <span class="block text-xs text-muted">{{ $item->variant_label }}</span>
                                <span class="mt-1 block text-xs text-muted">{{ trans($candidate->allows('return') ? 'returns::front.wizard.window_until' : 'returns::front.wizard.exchange_only_until', ['date' => $candidate->windowEndsAt->translatedFormat($dateFormat)]) }}</span>
                            </span>
                        </label>
                        <div class="flex flex-wrap items-end gap-3">
                            @if ($candidate->maxQty > 1)
                                <x-theme::select name="items[{{ $itemId }}][quantity]" :label="trans('returns::front.wizard.quantity')"
                                    :value="old('items.'.$itemId.'.quantity', $line['quantity'] ?? 1)"
                                    :options="collect(range(1, $candidate->maxQty))->mapWithKeys(fn ($qty) => [$qty => $qty])->all()" class="w-24" />
                            @else
                                <input type="hidden" name="items[{{ $itemId }}][quantity]" value="1">
                            @endif
                            @if ($candidate->exchangeVariants !== [])
                                <div x-show="type === 'exchange'" x-cloak>
                                    <x-theme::select name="items[{{ $itemId }}][exchange_variant_id]" :label="trans('returns::front.wizard.exchange_for')"
                                        :placeholder="trans('returns::front.wizard.choose_variant')"
                                        :value="old('items.'.$itemId.'.exchange_variant_id', $line['exchange_variant_id'] ?? null)"
                                        :options="collect($candidate->exchangeVariants)->mapWithKeys(fn ($variant) => [$variant['id'] => $variant['label']])->all()" />
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4"><x-theme::field-error name="items" /></div>
        </x-theme::card>

        <x-theme::card padding="p-5" class="space-y-4">
            <x-theme::select name="reason_code" :label="trans('returns::front.wizard.reason')" required
                :placeholder="trans('returns::front.wizard.choose_reason')" :value="old('reason_code', $draft->reasonCode)" :options="$reasons" />
            <p class="text-xs text-muted">{{ trans('returns::front.wizard.photo_note') }}</p>
            <x-theme::textarea name="comment" :label="trans('returns::front.wizard.comment')" :value="old('comment', $draft->comment)" maxlength="500" rows="3" />
        </x-theme::card>

        <div class="flex justify-end">
            <x-theme::button loading="loading">{{ trans('returns::front.wizard.continue') }}</x-theme::button>
        </div>
    </form>
@endsection
