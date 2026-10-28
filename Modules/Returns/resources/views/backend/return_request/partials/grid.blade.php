@php
    $langPath = 'returns::return_request.columns';
@endphp
{{ formStart(null, 'POST', 'admin.return_request.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $return)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'return_no')
                            <td>
                                {{ $return->return_no }}
                                @if ($return->needsAttention())
                                    <i class="fas fa-exclamation-triangle text-warning" data-toggle="tooltip" title="{{ $return->attention_reason }}"></i>
                                @endif
                            </td>
                        @elseif ($columnCode == 'order')
                            <td>{{ $return->sellerOrder?->sub_order_no }}</td>
                        @elseif ($columnCode == 'seller')
                            <td>{{ $return->seller?->displayName() }}</td>
                        @elseif ($columnCode == 'customer')
                            <td>{{ $return->customer?->displayName() }}</td>
                        @elseif ($columnCode == 'type')
                            <td>{{ $types[$return->type] ?? $return->type }}</td>
                        @elseif ($columnCode == 'reason_code')
                            <td>{{ $reasons[$return->reason_code] ?? $return->reason_code }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Returns\Support\ReturnOptions::badge($return->status) }}">{{ $statuses[$return->status] ?? $return->status }}</span></td>
                        @elseif ($columnCode == 'refund_amount')
                            <td class="text-right">{{ $return->refund_amount->format(true) }}</td>
                        @elseif ($columnCode == 'refund_status')
                            <td>{{ $return->refund_status ? trans('returns::returns.refund_statuses.'.$return->refund_status) : '-' }}</td>
                        @elseif ($columnCode == 'auto_approved')
                            <td>{{ $return->isAutoApproved() ? trans('core::core.labels.yes') : trans('core::core.labels.no') }}</td>
                        @elseif ($columnCode == 'created_at')
                            <td>{{ $return->created_at ? getFormatedDate($return->created_at, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $return->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.return_request.show')
                            <a href="{{ route('admin.return_request.show', updateUrlParams([$return->id])) }}" class="btn">
                                <span data-placement="left" data-toggle="tooltip" title="{{ trans('returns::return_request.labels.show') }}"><i class="fas fa-eye"></i></span>
                            </a>
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
