@extends('theme::layouts.backend.master')

@section('title')
{{ trans('notification::notification_template.titles.edit') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('notification::notification_template.titles.edit') }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.notification_template.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans('core::core.buttons.save') }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans('core::core.buttons.savencontinue') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
{{ formStart(null, 'PUT', 'admin.notification_template.update', updateUrlParams([$notificationTemplate->id]), ['id' => 'main_form']) }}
{{ normalHidden('snc', 0, 'snc', ['class' => 'snc']) }}
@include('notification::backend.notification_template.partials.form')
{{ formEnd() }}

@can('admin.notification_template.test_send')
<div class="row">
    <div class="col-lg-8">
        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('notification::notification_template.titles.test_send') }}</strong></div>
            <div class="card-body">
                <p class="text-muted">{{ trans('notification::notification_template.help.test_send') }}</p>
                @if ($notificationTemplate->channel !== 'database')
                    <form method="POST" action="{{ route('admin.notification_template.test_send', updateUrlParams([$notificationTemplate->id])) }}" class="form-inline">
                        @csrf
                        <label class="sr-only" for="test-destination">{{ trans('notification::notification_template.labels.destination') }}</label>
                        <input type="text" id="test-destination" name="destination" maxlength="255" required class="form-control mr-2 mb-2 {{ $errors->has('destination') ? 'is-invalid' : '' }}"
                            placeholder="{{ trans('notification::notification_template.labels.destination') }}" value="{{ old('destination') }}" style="min-width: 22rem">
                        <button type="submit" class="btn btn-outline-primary mb-2">{{ trans('notification::notification_template.labels.send_test') }}</button>
                        @error('destination')<label class="error d-block w-100">{{ $message }}</label>@enderror
                    </form>
                @endif

                <h6 class="mt-3">{{ trans('notification::notification_template.titles.recent_tests') }}</h6>
                @forelse ($recentTests as $test)
                    <div class="small">
                        <span class="badge badge-{{ $test->status === 'sent' ? 'success' : 'danger' }}">{{ trans('notification::notification_log.statuses.'.$test->status) }}</span>
                        {{ $test->destination }} · {{ getFormatedDate($test->created_at, getGridDateFormat()) }}
                        @if ($test->error)
                            · <span class="text-danger">{{ $test->error }}</span>
                        @endif
                    </div>
                @empty
                    <p class="small text-muted mb-0">{{ trans('notification::notification_template.messages.no_tests') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endcan
@stop
