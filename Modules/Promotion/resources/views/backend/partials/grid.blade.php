{{-- Shared promotion admin grid. $grid: coupon | flash_deal (route, lang file and view folder); each grid supplies partials/cells ($row, $columnCode) and partials/actions ($row). --}}
@php
    $langPath = 'promotion::'.$grid.'.columns';
@endphp
{{ formStart(null, 'POST', 'admin.'.$grid.'.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $row)
                <tr data-row="{{ $row->getKey() }}">
                    @foreach ($columns as $column)
                        @if ($column['checkbox_checked'] == 0)
                            @continue
                        @endif
                        @include('promotion::backend.'.$grid.'.partials.cells', ['row' => $row, 'columnCode' => $column['code']])
                    @endforeach
                    <td class="sticky-action">
                        @include('promotion::backend.'.$grid.'.partials.actions', ['row' => $row])
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
