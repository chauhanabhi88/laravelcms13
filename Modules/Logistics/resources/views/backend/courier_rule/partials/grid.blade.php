@php
    $langPath = 'logistics::courier_rule.columns';
@endphp
{{ formStart(null, 'POST', 'admin.courier_rule.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $rule)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'courier')
                            <td>{{ $rule->courier?->name }}</td>
                        @elseif ($columnCode == 'strategy')
                            <td>{{ $strategies[$rule->strategy] ?? $rule->strategy }}</td>
                        @elseif ($columnCode == 'conditions')
                            <td><small>{{ wordWrapper(collect((array) $rule->conditions)->map(fn ($value, $key) => $key.': '.(is_array($value) ? implode(',', $value) : $value))->implode('; ') ?: trans('logistics::courier_rule.labels.any')) }}</small></td>
                        @else
                            <td>{{ $rule->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.courier_rule.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.courier_rule.edit', updateUrlParams([$rule->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.courier_rule.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.courier_rule.delete', updateUrlParams([$rule->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.courier_rule.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$rule->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $rule->id }}" {{ $rule->status == config('core.enabled') ? 'checked' : '' }}>
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
