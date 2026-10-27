@php
    $langPath = 'catalog::catalog_brand.columns';
@endphp
{{ formStart(null, 'POST', 'admin.catalog_brand.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => auth()->user()->can('admin.catalog_brand.mass_delete')])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $catalogBrand)
                <tr>
                    @can('admin.catalog_brand.mass_delete')
                        <td>{{ normalCheckbox('selectedBrand[]', '', $errors, $catalogBrand->id, ['class' => 'select-item', 'data-id' => $catalogBrand->id, 'grid' => true]) }}</td>
                    @endcan
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'logo')
                            <td>
                                @if ($catalogBrand->logo)
                                    <img src="{{ getImageUrl(['module' => config('catalog.upload.brand'), 'image' => $catalogBrand->logo, 'defualt-image' => true]) }}" alt="" style="max-height: 32px">
                                @endif
                            </td>
                        @elseif ($columnCode == 'name')
                            <td>
                                {{ wordWrapper($catalogBrand->name) }}
                                @if ($catalogBrand->requested_by_seller_id)
                                    <div class="small text-muted">{{ trans('catalog::catalog_brand.labels.requested_by', ['id' => $catalogBrand->requested_by_seller_id]) }}</div>
                                @endif
                            </td>
                        @elseif ($columnCode == 'is_verified')
                            <td>{{ $yesNoOptions[$catalogBrand->is_verified] ?? '' }}</td>
                        @elseif ($columnCode == 'status')
                            <td>
                                @if ($catalogBrand->isPendingRequest())
                                    <span class="badge badge-warning">{{ trans('catalog::catalog_brand.labels.pending') }}</span>
                                @elseif ($catalogBrand->status == config('core.enabled'))
                                    <span class="badge badge-success">{{ trans('catalog::catalog_brand.labels.active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ trans('catalog::catalog_brand.labels.inactive') }}</span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'created_at')
                            <td>{{ getFormatedDate($catalogBrand->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $catalogBrand->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @if ($catalogBrand->isPendingRequest())
                            @can('admin.catalog_brand.approve')
                                <button type="button" class="btn btn-sm btn-success" data-approve-url="{{ route('admin.catalog_brand.approve', updateUrlParams([$catalogBrand->id])) }}">{{ trans('catalog::catalog_brand.labels.approve_button') }}</button>
                            @endcan
                        @endif
                        @can('admin.catalog_brand.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.catalog_brand.edit', updateUrlParams([$catalogBrand->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.catalog_brand.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.catalog_brand.delete', updateUrlParams([$catalogBrand->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.catalog_brand.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$catalogBrand->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $catalogBrand->id }}" {{ $catalogBrand->status == config('core.enabled') ? 'checked' : '' }}>
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
