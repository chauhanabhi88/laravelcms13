@php
    $langPath = 'product::product.columns';
@endphp
{{ formStart(null, 'POST', 'admin.inventory_movement.filters', updateUrlParams(), ['id' => 'search_frm']) }}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
<div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover text-nowrap">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $movement)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'created_at')
                            <td>{{ getFormatedDate($movement->created_at, getGridDateFormat()) }} {{ $movement->created_at?->format('H:i') }}</td>
                        @elseif ($columnCode == 'sku')
                            <td>
                                <code>{{ $movement->variant?->sku }}</code>
                                <div class="small text-muted">{{ $movement->variant?->seller_sku }}</div>
                            </td>
                        @elseif ($columnCode == 'product')
                            <td>
                                @can('admin.product.show')
                                    <a href="{{ route('admin.product.show', updateUrlParams([$movement->product_id])) }}">#{{ $movement->product_id }}</a>
                                @else
                                    #{{ $movement->product_id }}
                                @endcan
                                {{ wordWrapper((string) $movement->product?->name) }}
                            </td>
                        @elseif ($columnCode == 'type')
                            <td>{{ $movement->typeLabel() }}</td>
                        @elseif ($columnCode == 'bucket')
                            <td>{{ trans('product::product.buckets.'.$movement->bucket) }}</td>
                        @elseif ($columnCode == 'quantity')
                            <td class="{{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                        @elseif ($columnCode == 'reference')
                            <td>{{ $movement->reference_type ? class_basename($movement->reference_type).' #'.$movement->reference_id : '' }}</td>
                        @elseif ($columnCode == 'actor')
                            <td>
                                {{ trans('product::product.actor_types.'.($movement->actor_type ?? 'system')) }}
                                @if ($movement->actor_type === 'admin' && isset($adminNames[$movement->actor_id]))
                                    · {{ $adminNames[$movement->actor_id] }}
                                @elseif ($movement->actor_id)
                                    #{{ $movement->actor_id }}
                                @endif
                            </td>
                        @else
                            <td>{{ $movement->$columnCode }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
