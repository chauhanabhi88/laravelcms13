@php
    $langPath = 'logistics::pickup_batch.columns';
@endphp
{{ formStart(null, 'POST', 'admin.pickup_batch.filters', updateUrlParams(), ['id' => 'search_frm']) }}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
{!! formEnd() !!}
<div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $batch)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'manifest_no')
                            <td>
                                @can('admin.pickup_batch.show')
                                    <a href="{{ route('admin.pickup_batch.show', updateUrlParams([$batch->id])) }}">{{ $batch->manifest_no }}</a>
                                @else
                                    {{ $batch->manifest_no }}
                                @endcan
                            </td>
                        @elseif ($columnCode == 'seller')
                            <td>{{ $batch->seller?->displayName() }} <small class="text-muted">{{ $batch->seller?->code }}</small></td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ match ($batch->status) { 'picked_up' => 'success', 'partially_picked' => 'warning', 'open' => 'danger', default => 'info' } }}">{{ $statuses[$batch->status] ?? $batch->status }}</span></td>
                        @elseif ($columnCode == 'pickup_date')
                            <td class="text-nowrap">{{ $batch->pickup_date?->format('d M Y') }}</td>
                        @elseif ($columnCode == 'created_at')
                            <td class="text-nowrap">{{ getFormatedDate($batch->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $batch->$columnCode }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
