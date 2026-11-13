{{--
    Failed delivery attempt on one package (FR-LOG-008), included by the order page with
    @includeIf. Open case: the customer picks re-attempt, a new address / phone, or cancel.
    Answered case: what they asked for, while the package is still with the courier.
--}}
@php
    $issue = app(\Modules\Logistics\Support\DeliveryIssues::class)->latestFor($sellerOrder);
    $inTransit = in_array($sellerOrder->status, [
        \Modules\Order\Models\SellerOrder::STATUS_SHIPPED,
        \Modules\Order\Models\SellerOrder::STATUS_OUT_FOR_DELIVERY,
        \Modules\Order\Models\SellerOrder::STATUS_NDR,
    ], true);
    $isOpen = $issue?->isOpen() && $inTransit;
    $isAnswered = $issue !== null && ! $issue->isOpen() && $issue->customer_action !== null && $inTransit;
@endphp

@if ($isOpen)
    <div class="border-t border-line bg-deal-soft/40 px-4 py-4 sm:px-5" data-delivery-issue="{{ $sellerOrder->id }}"
        x-data="{ action: @js(old('action', \Modules\Logistics\Models\NdrCase::ACTION_REATTEMPT)) }">
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-ink">
            <x-theme::icon name="alert" size="size-4" class="text-deal" />
            {{ trans('logistics::front.delivery_issue.title') }}
        </h3>
        <p class="mt-1 text-xs text-ink-soft">
            {{ trans_choice('logistics::front.delivery_issue.intro', $issue->attempts, ['count' => $issue->attempts]) }}
            @if ($issue->reason)
                {{ trans('logistics::front.delivery_issue.reason', ['reason' => $issue->reason]) }}
            @endif
        </p>

        <form method="POST" action="{{ frontRoute('customer.orders.delivery_issue', ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]) }}" class="mt-3 space-y-3">
            @csrf
            <fieldset class="space-y-1.5">
                <legend class="sr-only">{{ trans('logistics::front.delivery_issue.choose') }}</legend>
                @foreach ((array) config('logistics.ndr_actions') as $action)
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink">
                        <input type="radio" name="action" value="{{ $action }}" x-model="action" class="size-4 border-line-strong text-accent focus:ring-accent/30">
                        {{ trans('logistics::front.delivery_issue.actions.'.$action) }}
                    </label>
                @endforeach
            </fieldset>

            <div x-show="action === @js(\Modules\Logistics\Models\NdrCase::ACTION_CHANGE_ADDRESS)" x-cloak class="grid gap-3 sm:grid-cols-2">
                <label class="block text-xs font-medium text-ink-soft sm:col-span-2">
                    {{ trans('logistics::front.delivery_issue.address1') }}
                    <input type="text" name="address1" value="{{ old('address1') }}" maxlength="200" x-bind:required="action === @js(\Modules\Logistics\Models\NdrCase::ACTION_CHANGE_ADDRESS)"
                        class="mt-1 h-10 w-full rounded-lg border-line-strong bg-surface text-sm text-ink">
                </label>
                <label class="block text-xs font-medium text-ink-soft">
                    {{ trans('logistics::front.delivery_issue.address2') }}
                    <input type="text" name="address2" value="{{ old('address2') }}" maxlength="200"
                        class="mt-1 h-10 w-full rounded-lg border-line-strong bg-surface text-sm text-ink">
                </label>
                <label class="block text-xs font-medium text-ink-soft">
                    {{ trans('logistics::front.delivery_issue.phone') }}
                    <input type="tel" name="phone" value="{{ old('phone') }}" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10"
                        class="mt-1 h-10 w-full rounded-lg border-line-strong bg-surface text-sm text-ink">
                </label>
            </div>

            <label class="block text-xs font-medium text-ink-soft">
                {{ trans('logistics::front.delivery_issue.comments') }}
                <input type="text" name="comments" value="{{ old('comments') }}" maxlength="255"
                    class="mt-1 h-10 w-full rounded-lg border-line-strong bg-surface text-sm text-ink">
            </label>

            @foreach (['action', 'address1', 'address2', 'phone', 'comments'] as $field)
                @error($field)
                    <p class="text-xs text-danger">{{ $message }}</p>
                @enderror
            @endforeach

            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-semibold text-accent-ink hover:bg-accent-hover">
                {{ trans('logistics::front.delivery_issue.submit') }}
            </button>
        </form>
    </div>
@elseif ($isAnswered)
    <p class="border-t border-line px-4 py-3 text-xs text-ink-soft sm:px-5" data-delivery-issue-answered="{{ $sellerOrder->id }}">
        {{ trans('logistics::front.delivery_issue.answered', ['action' => trans('logistics::front.delivery_issue.actions.'.$issue->customer_action)]) }}
    </p>
@endif
