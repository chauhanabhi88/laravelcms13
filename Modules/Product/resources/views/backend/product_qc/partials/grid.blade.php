@php
    $langPath = 'product::product.columns';
@endphp
{{ formStart(null, 'POST', 'admin.product_qc.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @can('admin.product_qc.bulk')
                    <th data-sortable="false"><input type="checkbox" id="qc-select-all" aria-label="{{ trans('product::product.labels.selected') }}"></th>
                @endcan
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $product)
                <tr>
                    @can('admin.product_qc.bulk')
                        <td><input type="checkbox" class="qc-select" value="{{ $product->id }}" aria-label="#{{ $product->id }}"></td>
                    @endcan
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
                        @can('admin.product_qc.review')
                            <button type="button" onclick="setLocation('{{ route('admin.product_qc.review', updateUrlParams([$product->id])) }}');" class="btn btn-sm btn-primary">{{ trans('product::product.buttons.review') }}</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 2 }}">{{ trans('product::product.labels.queue_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
