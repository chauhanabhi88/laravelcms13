@php
    $langPath = 'payment::webhook_event.columns';
@endphp
{{ formStart(null, 'POST', 'admin.webhook_event.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
            @forelse ($collection as $event)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'provider')
                            <td>{{ $providers[$event->provider] ?? $event->provider }}</td>
                        @elseif ($columnCode == 'signature_valid')
                            <td>
                                @if ($event->hasValidSignature())
                                    <span class="badge badge-success">{{ trans('payment::webhook_event.labels.valid') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ trans('payment::webhook_event.labels.invalid') }}</span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'status')
                            <td><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::webhookBadge($event->status) }}">{{ $statuses[$event->status] ?? $event->status }}</span></td>
                        @elseif ($columnCode == 'error')
                            <td>{{ \Illuminate\Support\Str::limit((string) $event->error, 60) ?: '-' }}</td>
                        @elseif (in_array($columnCode, ['created_at', 'processed_at'], true))
                            <td>{{ $event->$columnCode ? getFormatedDate($event->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $event->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.webhook_event.show')
                            <button type="button" onclick="setLocation('{{ route('admin.webhook_event.show', updateUrlParams([$event->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('payment::webhook_event.labels.show') }}"><i class="fas fa-eye"></i></span></button>
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
