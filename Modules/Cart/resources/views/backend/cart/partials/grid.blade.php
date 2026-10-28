@php
    $langPath = 'cart::cart.columns';
    $stateBadges = ['active' => 'success', 'abandoned' => 'warning', 'empty' => 'secondary'];
@endphp
{{ formStart(null, 'POST', 'admin.cart.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $cart)
                @php
                    $state = \Modules\Cart\Support\CartOptions::stateOf($cart, (int) $cart->active_lines);
                @endphp
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'customer')
                            <td>
                                @if ($cart->customer)
                                    {{ $cart->customer->displayName() }}
                                    <br><small class="text-muted">{{ $cart->customer->contact_number ?: $cart->customer->email }}</small>
                                @else
                                    <span class="text-muted">{{ trans('cart::cart.labels.guest') }}</span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'type')
                            <td>{{ $types[$cart->type] ?? $cart->type }}</td>
                        @elseif ($columnCode == 'items')
                            <td>{{ trans('cart::cart.labels.lines', ['lines' => (int) $cart->active_lines, 'units' => (int) $cart->active_quantity]) }}</td>
                        @elseif ($columnCode == 'saved')
                            <td>{{ (int) $cart->saved_lines }}</td>
                        @elseif ($columnCode == 'state')
                            <td><span class="badge badge-{{ $stateBadges[$state] }}">{{ $states[$state] }}</span></td>
                        @elseif (in_array($columnCode, ['last_activity_at', 'reminded_at', 'created_at'], true))
                            <td>{{ $cart->$columnCode ? getFormatedDate($cart->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $cart->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.cart.show')
                            <button type="button" onclick="setLocation('{{ route('admin.cart.show', updateUrlParams([$cart->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('cart::cart.labels.show') }}"><i class="fas fa-eye"></i></span></button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 1 }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
