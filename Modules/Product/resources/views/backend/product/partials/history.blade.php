{{-- Status history card. $product, $adminNames. --}}
@php
    $none = trans('product::product.labels.none');
@endphp
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.history') }}</strong></div>
    <div class="card-body">
        @forelse ($product->statusHistories->sortByDesc('id') as $history)
            <div class="border-bottom pb-2 mb-2 small">
                <div>
                    @if (! empty($history->meta['action']))
                        <strong>{{ trans('product::product.history_actions.'.$history->meta['action']) }}</strong>
                    @else
                        <strong>{{ trans('product::product.statuses.'.$history->to_status) }}</strong>
                        @if ($history->from_status)
                            <span class="text-muted">← {{ trans('product::product.statuses.'.$history->from_status) }}</span>
                        @endif
                    @endif
                </div>
                <div class="text-muted">
                    {{ getFormatedDate($history->created_at, getGridDateFormat()) }} {{ $history->created_at?->format('H:i') }} ·
                    {{ trans('product::product.actor_types.'.$history->actor_type) }}@if ($history->actor_type === 'admin' && isset($adminNames[$history->actor_id])): {{ $adminNames[$history->actor_id] }}@endif
                </div>
                @if ($history->reason)
                    <div class="mt-1">{{ $history->reason }}</div>
                @endif
            </div>
        @empty
            <p class="mb-0 text-muted">{{ $none }}</p>
        @endforelse
    </div>
</div>
