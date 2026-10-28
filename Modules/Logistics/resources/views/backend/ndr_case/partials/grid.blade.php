@php
    $langPath = 'logistics::ndr_case.columns';
@endphp
{{ formStart(null, 'POST', 'admin.ndr.filters', updateUrlParams(), ['id' => 'search_frm']) }}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
{!! formEnd() !!}
<div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
                <th data-sortable="false">{{ trans('logistics::ndr_case.labels.act') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $case)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'awb')
                            <td>
                                @can('admin.shipment.show')
                                    <a href="{{ route('admin.shipment.show', updateUrlParams([$case->shipment_id])) }}">{{ $case->shipment?->awb }}</a>
                                @else
                                    {{ $case->shipment?->awb }}
                                @endcan
                            </td>
                        @elseif ($columnCode == 'sub_order_no')
                            <td>{{ $case->shipment?->sellerOrder?->sub_order_no }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Logistics\Support\LogisticsOptions::ndrBadge($case->status) }}">{{ $statuses[$case->status] ?? $case->status }}</span></td>
                        @elseif ($columnCode == 'customer_action')
                            <td>{{ $case->customer_action ? ($actions[$case->customer_action] ?? $case->customer_action) : '-' }}@if ($case->actioned_by) <small class="text-muted">({{ $case->actioned_by }})</small>@endif</td>
                        @elseif ($columnCode == 'created_at')
                            <td class="text-nowrap">{{ getFormatedDate($case->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $case->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td style="min-width: 260px">
                        @if ($case->isOpen())
                            @can('admin.ndr.act')
                                <form method="POST" action="{{ route('admin.ndr.act', updateUrlParams([$case->id])) }}">
                                    @csrf
                                    <select name="action" class="custom-select custom-select-sm mb-1 ndr-action">
                                        @foreach ($actions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="ndr-address" style="display: none;">
                                        <input type="text" name="phone" class="form-control form-control-sm mb-1" maxlength="10" placeholder="{{ trans('logistics::ndr_case.labels.phone') }}">
                                        <input type="text" name="address1" class="form-control form-control-sm mb-1" maxlength="200" placeholder="{{ trans('logistics::ndr_case.labels.address1') }}">
                                        <input type="text" name="address2" class="form-control form-control-sm mb-1" maxlength="200" placeholder="{{ trans('logistics::ndr_case.labels.address2') }}">
                                    </div>
                                    <input type="text" name="comments" class="form-control form-control-sm mb-1" maxlength="255" placeholder="{{ trans('logistics::ndr_case.labels.comments') }}">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ trans('logistics::ndr_case.labels.submit') }}</button>
                                </form>
                            @endcan
                        @else
                            -
                        @endif
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
