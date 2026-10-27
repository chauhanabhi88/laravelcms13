@php
    $langPath = 'storefront::product_collection.columns';
@endphp
{{ formStart(null, 'POST', 'admin.product_collection.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => auth()->user()->can('admin.product_collection.mass_delete')])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $productCollection)
                <tr>
                    @can('admin.product_collection.mass_delete')
                        <td>{{ normalCheckbox('selectedCollection[]', '', $errors, $productCollection->id, ['class' => 'select-item', 'data-id' => $productCollection->id, 'grid' => true]) }}</td>
                    @endcan
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'banner_image')
                            <td>
                                @if ($productCollection->banner_image)
                                    <img src="{{ $productCollection->bannerUrl() }}" alt="" style="max-height: 32px">
                                @endif
                            </td>
                        @elseif ($columnCode == 'name')
                            <td>{{ wordWrapper((string) $productCollection->name) }}</td>
                        @elseif ($columnCode == 'type')
                            <td>{{ trans('storefront::product_collection.types.'.$productCollection->type) }}</td>
                        @elseif ($columnCode == 'sort')
                            <td>{{ trans('storefront::product_collection.sorts.'.$productCollection->sort) }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ $productCollection->isEnabled() ? 'success' : 'secondary' }}">{{ $statusOptions[$productCollection->status] ?? '' }}</span></td>
                        @elseif ($columnCode == 'updated_at')
                            <td>{{ getFormatedDate($productCollection->updated_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $productCollection->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @if ($productCollection->isEnabled())
                            <a href="{{ frontRoute('storefront.collection', [$productCollection->slug]) }}" target="_blank" rel="noopener" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('storefront::product_collection.labels.view') }}"><i class="fas fa-external-link-alt"></i></span></a>
                        @endif
                        @can('admin.product_collection.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.product_collection.edit', updateUrlParams([$productCollection->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.product_collection.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.product_collection.delete', updateUrlParams([$productCollection->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.product_collection.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$productCollection->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $productCollection->id }}" {{ $productCollection->isEnabled() ? 'checked' : '' }}>
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
