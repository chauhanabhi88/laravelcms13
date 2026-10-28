@extends('theme::layouts.backend.master')

@php
    $statusBadges = ['queued' => 'info', 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'secondary'];
    $reason = $log->error && trans()->has('notification::notification_log.reasons.'.$log->error) ? trans('notification::notification_log.reasons.'.$log->error) : $log->error;
@endphp

@section('title')
{{ trans('notification::notification_log.titles.show', ['id' => $log->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('notification::notification_log.titles.show', ['id' => $log->id]) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.notification_log.index', updateUrlParams()) }}')">{{ trans('notification::notification_log.labels.back') }}</button>
                @can('admin.notification_log.retry')
                    @if ($log->isRetryable())
                        <form method="POST" action="{{ route('admin.notification_log.retry', updateUrlParams([$log->id])) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-fw">{{ trans('notification::notification_log.labels.retry') }}</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('notification::notification_log.titles.details') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.status') }}</dt>
                    <dd class="col-sm-8"><span class="badge badge-{{ $statusBadges[$log->status] ?? 'secondary' }}">{{ $statuses[$log->status] ?? $log->status }}</span></dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.event_key') }}</dt>
                    <dd class="col-sm-8"><code>{{ $log->event_key }}</code> — {{ \Modules\Notification\Support\NotificationOptions::eventLabel($log->event_key) }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.channel') }}</dt>
                    <dd class="col-sm-8">{{ $channels[$log->channel] ?? $log->channel }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.recipient') }}</dt>
                    <dd class="col-sm-8">@include('notification::backend.notification_log.partials.recipient')</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.destination') }}</dt>
                    <dd class="col-sm-8">{{ $log->destination ?: '-' }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.error') }}</dt>
                    <dd class="col-sm-8 {{ $log->status === 'failed' ? 'text-danger' : '' }}">{{ $reason ?: '-' }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.provider_message_id') }}</dt>
                    <dd class="col-sm-8">{{ $log->provider_message_id ?: '-' }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.notification_id') }}</dt>
                    <dd class="col-sm-8"><small>{{ $log->notification_id ?: '-' }}</small></dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.created_at') }}</dt>
                    <dd class="col-sm-8">{{ getFormatedDate($log->created_at, getGridDateFormat()) }}</dd>

                    <dt class="col-sm-4">{{ trans('notification::notification_log.labels.sent_at') }}</dt>
                    <dd class="col-sm-8">{{ $log->sent_at ? getFormatedDate($log->sent_at, getGridDateFormat()) : '-' }}</dd>
                </dl>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="card card-info card-outline">
                <div class="card-header"><strong>{{ trans('notification::notification_log.titles.related') }}</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach ($related as $other)
                                <tr>
                                    <td><a href="{{ route('admin.notification_log.show', updateUrlParams([$other->id])) }}">#{{ $other->id }}</a></td>
                                    <td>{{ $channels[$other->channel] ?? $other->channel }}</td>
                                    <td><span class="badge badge-{{ $statusBadges[$other->status] ?? 'secondary' }}">{{ $statuses[$other->status] ?? $other->status }}</span></td>
                                    <td>{{ $other->destination ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('notification::notification_log.titles.payload') }}</strong></div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</div>
@stop
