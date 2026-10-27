@php
    $langPath = 'storefront::home_section.columns';
    $dateFormat = (string) config('core.encrypt.php_datepicker_format', 'd-m-Y');
@endphp
{{ formStart(null, 'POST', 'admin.home_section.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => auth()->user()->can('admin.home_section.mass_delete')])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $homeSection)
                <tr>
                    @can('admin.home_section.mass_delete')
                        <td>{{ normalCheckbox('selectedHomeSection[]', '', $errors, $homeSection->id, ['class' => 'select-item', 'data-id' => $homeSection->id, 'grid' => true]) }}</td>
                    @endcan
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'type')
                            <td>{{ $typeOptions[$homeSection->type] ?? $homeSection->type }}</td>
                        @elseif ($columnCode == 'title')
                            <td>{{ wordWrapper((string) $homeSection->title) }}</td>
                        @elseif ($columnCode == 'sort_order')
                            <td>
                                {{ $homeSection->sort_order }}
                                @can('admin.home_section.reorder')
                                    <button type="button" class="btn btn-sm px-1" data-reorder="up" data-id="{{ $homeSection->id }}" data-toggle="tooltip" title="{{ trans('storefront::home_section.labels.move_up') }}"><i class="fas fa-arrow-up"></i></button>
                                    <button type="button" class="btn btn-sm px-1" data-reorder="down" data-id="{{ $homeSection->id }}" data-toggle="tooltip" title="{{ trans('storefront::home_section.labels.move_down') }}"><i class="fas fa-arrow-down"></i></button>
                                @endcan
                            </td>
                        @elseif ($columnCode == 'schedule')
                            <td>
                                @if ($homeSection->starts_at && $homeSection->ends_at)
                                    {{ trans('storefront::home_section.labels.between', ['from' => $homeSection->starts_at->format($dateFormat), 'to' => $homeSection->ends_at->format($dateFormat)]) }}
                                @elseif ($homeSection->starts_at)
                                    {{ trans('storefront::home_section.labels.from', ['date' => $homeSection->starts_at->format($dateFormat)]) }}
                                @elseif ($homeSection->ends_at)
                                    {{ trans('storefront::home_section.labels.until', ['date' => $homeSection->ends_at->format($dateFormat)]) }}
                                @else
                                    {{ trans('storefront::home_section.labels.always') }}
                                @endif
                            </td>
                        @elseif ($columnCode == 'status')
                            <td>
                                <span class="badge badge-{{ $homeSection->isEnabled() ? 'success' : 'secondary' }}">{{ $statusOptions[$homeSection->status] ?? '' }}</span>
                            </td>
                        @elseif ($columnCode == 'updated_at')
                            <td>{{ getFormatedDate($homeSection->updated_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $homeSection->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.home_section.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.home_section.edit', updateUrlParams([$homeSection->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.home_section.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.home_section.delete', updateUrlParams([$homeSection->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.home_section.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$homeSection->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $homeSection->id }}" {{ $homeSection->isEnabled() ? 'checked' : '' }}>
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 2 }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
