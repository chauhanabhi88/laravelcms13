@php
    $langPath = 'seller::seller.columns';
@endphp
{{ formStart(null, 'POST', 'admin.seller.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $seller)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @include('seller::backend.seller.partials.grid-row')
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.seller.show')
                            <button type="button" onclick="setLocation('{{ route('admin.seller.show', updateUrlParams([$seller->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('seller::seller.labels.view') }}"><i class="fas fa-eye"></i></span></button>
                        @endcan
                        @can('admin.seller.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.seller.edit', updateUrlParams([$seller->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
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
