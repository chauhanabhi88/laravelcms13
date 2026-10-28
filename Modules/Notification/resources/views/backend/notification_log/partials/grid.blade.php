@php
    $langPath = 'notification::notification_log.columns';
    $statusBadges = ['queued' => 'info', 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'secondary'];
@endphp
{{ formStart(null, 'POST', 'admin.notification_log.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $log)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'created_at' || $columnCode == 'sent_at')
                            <td>{{ $log->$columnCode ? getFormatedDate($log->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @elseif ($columnCode == 'event_key')
                            <td><code>{{ $log->event_key }}</code></td>
                        @elseif ($columnCode == 'channel')
                            <td>{{ $channels[$log->channel] ?? $log->channel }}</td>
                        @elseif ($columnCode == 'recipient')
                            <td>@include('notification::backend.notification_log.partials.recipient')</td>
                        @elseif ($columnCode == 'destination')
                            <td>{{ $log->destination ?: '-' }}</td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ $statusBadges[$log->status] ?? 'secondary' }}">{{ $statuses[$log->status] ?? $log->status }}</span></td>
                        @elseif ($columnCode == 'error')
                            <td>{{ $log->error ? wordWrapper(\Illuminate\Support\Str::limit(trans()->has('notification::notification_log.reasons.'.$log->error) ? trans('notification::notification_log.reasons.'.$log->error) : $log->error, 80)) : '-' }}</td>
                        @else
                            <td>{{ $log->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.notification_log.show')
                            <button type="button" onclick="setLocation('{{ route('admin.notification_log.show', updateUrlParams([$log->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('notification::notification_log.labels.show') }}"><i class="fas fa-eye"></i></span></button>
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
