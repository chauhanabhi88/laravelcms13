@php
    $langPath = 'returns::return_claim.columns';
@endphp
{{ formStart(null, 'POST', 'admin.return_claim.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $claim)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'reference')
                            <td>{{ $claim->returnRequest?->return_no ?? $claim->sellerOrder?->sub_order_no }}</td>
                        @elseif ($columnCode == 'source')
                            <td>{{ trans('returns::return_claim.sources.'.$claim->source) }}</td>
                        @elseif ($columnCode == 'seller')
                            <td>{{ $claim->seller?->displayName() }}</td>
                        @elseif ($columnCode == 'reason')
                            <td>{{ $reasons[$claim->reason] ?? $claim->reason }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Returns\Support\ReturnOptions::claimBadge($claim->status) }}">{{ $statuses[$claim->status] ?? $claim->status }}</span></td>
                        @elseif ($columnCode == 'resolution_amount')
                            <td class="text-right">{{ $claim->resolution_amount?->format(true) ?? '-' }}</td>
                        @elseif (in_array($columnCode, ['created_at', 'decided_at'], true))
                            <td>{{ $claim->$columnCode ? getFormatedDate($claim->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $claim->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.return_claim.show')
                            <a href="{{ route('admin.return_claim.show', updateUrlParams([$claim->id])) }}" class="btn">
                                <span data-placement="left" data-toggle="tooltip" title="{{ trans('returns::return_claim.labels.show') }}"><i class="fas fa-eye"></i></span>
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
