{{-- Wizard progress (plan M15 §5.3) and the package being returned. Expects $current (1–4), $order, $sellerOrder. --}}
<x-theme::stepper class="mb-5" :current="$current" :steps="[
    trans('returns::front.wizard.steps.reason'),
    trans('returns::front.wizard.steps.photos'),
    trans('returns::front.wizard.steps.refund'),
    trans('returns::front.wizard.steps.confirm'),
]" />

<p class="mb-5 text-sm text-muted">
    {{ trans('returns::front.wizard.package', ['order_no' => $sellerOrder->sub_order_no, 'store' => $sellerOrder->seller?->displayName()]) }}
    · <a href="{{ frontRoute('customer.orders.show', ['orderNo' => $order->order_no]) }}" class="font-medium text-accent hover:underline">{{ trans('returns::front.wizard.back_to_order') }}</a>
</p>

@if (session('error'))
    <x-theme::alert type="error" class="mb-5">{{ session('error') }}</x-theme::alert>
@endif
