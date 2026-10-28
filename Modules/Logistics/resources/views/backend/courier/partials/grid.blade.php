@php
    $langPath = 'logistics::courier.columns';
@endphp
{{ formStart(null, 'POST', 'admin.courier.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $courier)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'driver')
                            <td>{{ $drivers[$courier->driver] ?? $courier->driver }}</td>
                        @elseif ($columnCode == 'supports_cod')
                            <td>{{ $courier->supportsCod() ? trans('core::core.options.yesno.yes') : trans('core::core.options.yesno.no') }}</td>
                        @elseif ($columnCode == 'supports_reverse')
                            <td>{{ $courier->supportsReverse() ? trans('core::core.options.yesno.yes') : trans('core::core.options.yesno.no') }}</td>
                        @else
                            <td>{{ $courier->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.courier.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.courier.edit', updateUrlParams([$courier->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.courier.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.courier.delete', updateUrlParams([$courier->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.courier.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$courier->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $courier->id }}" {{ $courier->status == config('core.enabled') ? 'checked' : '' }}>
                                    <span class="slider round"></span>
                                </label>
                            </span>
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
