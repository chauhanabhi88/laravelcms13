@extends('theme::layouts.backend.master')

@section('title')
{{ trans('payment::webhook_event.titles.show', ['id' => $event->id]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('payment::webhook_event.titles.show', ['id' => $event->id]) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right d-flex">
                @if ($event->isReplayable())
                    @can('admin.webhook_event.replay')
                        <form method="POST" action="{{ route('admin.webhook_event.replay', updateUrlParams([$event->id])) }}" class="mr-2"
                            onsubmit="return confirm(@js(trans('payment::webhook_event.labels.confirm_replay')));">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-fw">{{ trans('payment::webhook_event.labels.replay') }}</button>
                        </form>
                    @endcan
                @endif
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.webhook_event.index', updateUrlParams()) }}')">{{ trans('payment::webhook_event.labels.back') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-4">
        <div class="card card-info card-outline mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.provider') }}</dt>
                    <dd class="col-sm-7">{{ $event->provider }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.event_type') }}</dt>
                    <dd class="col-sm-7">{{ $event->event_type ?? '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.event_id') }}</dt>
                    <dd class="col-sm-7"><code>{{ $event->event_id }}</code></dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.signature') }}</dt>
                    <dd class="col-sm-7">{{ $event->hasValidSignature() ? trans('payment::webhook_event.labels.valid') : trans('payment::webhook_event.labels.invalid') }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.status') }}</dt>
                    <dd class="col-sm-7"><span class="badge badge-{{ \Modules\Payment\Support\PaymentOptions::webhookBadge($event->status) }}">{{ $statuses[$event->status] ?? $event->status }}</span></dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.attempts') }}</dt>
                    <dd class="col-sm-7">{{ $event->attempts }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.received_at') }}</dt>
                    <dd class="col-sm-7">{{ $event->created_at ? getFormatedDate($event->created_at, getGridDateFormat()) : '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.processed_at') }}</dt>
                    <dd class="col-sm-7">{{ $event->processed_at ? getFormatedDate($event->processed_at, getGridDateFormat()) : '-' }}</dd>
                    <dt class="col-sm-5">{{ trans('payment::webhook_event.labels.error') }}</dt>
                    <dd class="col-sm-7">{{ $event->error ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card card-info card-outline mb-3">
            <div class="card-header"><strong>{{ trans('payment::webhook_event.titles.payload') }}</strong></div>
            <div class="card-body">
                @if ($event->payload === null)
                    <p class="text-muted mb-0">{{ trans('payment::webhook_event.labels.no_payload') }}</p>
                @else
                    <pre class="mb-0" style="max-height: 600px; overflow: auto; white-space: pre-wrap;">{{ json_encode(\Modules\Core\Support\IntegrationHttp::redact($event->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </div>
        </div>
    </div>
</div>
@stop
