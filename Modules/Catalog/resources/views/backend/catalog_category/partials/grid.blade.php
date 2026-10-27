@php
    $langPath = 'catalog::catalog_category.columns';
@endphp
{{ formStart(null, 'POST', 'admin.catalog_category.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => auth()->user()->can('admin.catalog_category.mass_delete')])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $catalogCategory)
                <tr>
                    @can('admin.catalog_category.mass_delete')
                        <td>{{ normalCheckbox('selectedCategory[]', '', $errors, $catalogCategory->id, ['class' => 'select-item', 'data-id' => $catalogCategory->id, 'grid' => true]) }}</td>
                    @endcan
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'name')
                            <td>{{ wordWrapper($catalogCategory->name) }}</td>
                        @elseif ($columnCode == 'parent_id')
                            <td>{{ $catalogCategory->parent?->name ?? '—' }}</td>
                        @elseif ($columnCode == 'depth')
                            <td>{{ $catalogCategory->depth + 1 }}</td>
                        @elseif (in_array($columnCode, ['is_leaf', 'show_in_menu']))
                            <td>{{ $yesNoOptions[$catalogCategory->$columnCode] ?? '' }}</td>
                        @elseif ($columnCode == 'updated_at')
                            <td>{{ getFormatedDate($catalogCategory->updated_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $catalogCategory->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.catalog_category.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.catalog_category.edit', updateUrlParams([$catalogCategory->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.catalog_category.attributes')
                            <button type="button" onclick="setLocation('{{ route('admin.catalog_category.attributes', updateUrlParams([$catalogCategory->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('catalog::catalog_category.labels.manage_attributes') }}"><i class="fas fa-list"></i></span></button>
                        @endcan
                        @can('admin.catalog_category.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.catalog_category.delete', updateUrlParams([$catalogCategory->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.catalog_category.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$catalogCategory->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $catalogCategory->id }}" {{ $catalogCategory->status == config('core.enabled') ? 'checked' : '' }}>
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
