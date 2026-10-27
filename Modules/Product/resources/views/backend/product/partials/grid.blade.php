@php
    $langPath = 'product::product.columns';
@endphp
{{ formStart(null, 'POST', 'admin.product.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $product)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @include('product::backend.product.partials.grid-cell')
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.product.show')
                            <button type="button" onclick="setLocation('{{ route('admin.product.show', updateUrlParams([$product->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('product::product.labels.view') }}"><i class="fas fa-eye"></i></span></button>
                        @endcan
                        @if ($product->isInQcQueue())
                            @can('admin.product_qc.review')
                                <button type="button" onclick="setLocation('{{ route('admin.product_qc.review', updateUrlParams([$product->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('product::product.labels.review') }}"><i class="fas fa-clipboard-check"></i></span></button>
                            @endcan
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
{!! formEnd() !!}
