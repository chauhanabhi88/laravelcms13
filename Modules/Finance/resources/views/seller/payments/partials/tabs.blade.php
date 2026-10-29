{{-- Supplier payments sub-navigation. $current: overview | statement | payouts | invoices --}}
<nav class="flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1" aria-label="{{ trans('finance::seller.menu.section') }}">
    @foreach (['overview' => 'seller.payments.overview', 'statement' => 'seller.payments.statement', 'payouts' => 'seller.payments.payouts', 'invoices' => 'seller.payments.invoices'] as $key => $route)
        <a href="{{ frontRoute($route) }}"
            @class(['shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-accent-soft text-accent' => $current === $key, 'text-ink-soft hover:bg-surface-alt' => $current !== $key])
            @if ($current === $key) aria-current="page" @endif>{{ trans('finance::seller.menu.'.$key) }}</a>
    @endforeach
</nav>
