@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::courier_rule.titles.simulate') }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('logistics::courier_rule.titles.simulate') }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.courier_rule.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-5">
        <div class="card card-info card-outline mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.courier_rule.simulate', updateUrlParams()) }}" id="simulate_form">
                    @csrf
                    {{ normalText('origin_pincode', 'logistics::courier_rule.labels.origin_pincode', $errors, old('origin_pincode', request('origin_pincode')), ['class' => 'form-control required', 'maxlength' => 6]) }}
                    {{ normalText('destination_pincode', 'logistics::courier_rule.labels.destination_pincode', $errors, old('destination_pincode', request('destination_pincode')), ['class' => 'form-control required', 'maxlength' => 6]) }}
                    {{ normalText('weight_grams', 'logistics::courier_rule.labels.weight_grams', $errors, old('weight_grams', request('weight_grams', 500)), ['class' => 'form-control required', 'maxlength' => 7]) }}
                    {{ normalSelect('payment', 'logistics::courier_rule.labels.payment', $errors, \Modules\Logistics\Support\LogisticsOptions::paymentModes(), old('payment', request('payment', 'cod'))) }}
                    <div class="row">
                        <div class="col-6">{{ normalText('seller_id', 'logistics::courier_rule.labels.seller_id', $errors, old('seller_id', request('seller_id')), ['class' => 'form-control', 'maxlength' => 10]) }}</div>
                        <div class="col-6">{{ normalText('category_id', 'logistics::courier_rule.labels.category_id', $errors, old('category_id', request('category_id')), ['class' => 'form-control', 'maxlength' => 10]) }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-fw">{{ trans('logistics::courier_rule.labels.run') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        @if ($result !== null)
            <div class="card card-info card-outline mb-3" data-simulation-result>
                <div class="card-header"><strong>{{ trans('logistics::courier_rule.titles.result') }}</strong></div>
                <div class="card-body">
                    @if ($result['error'])
                        <div class="alert alert-danger">{{ $result['error'] }}</div>
                    @endif
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.zone') }}</dt>
                        <dd class="col-sm-7">{{ $result['context']->zone ?? '-' }}</dd>
                        <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.matched_rule') }}</dt>
                        <dd class="col-sm-7" data-matched-rule>{{ $result['rule'] ? '#'.$result['rule']->id.' '.$result['rule']->name : trans('logistics::courier_rule.labels.no_rule') }}</dd>
                        @if ($result['assignment'])
                            <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.chosen_courier') }}</dt>
                            <dd class="col-sm-7" data-chosen-courier>{{ $result['assignment']->courier->name }} ({{ $strategies[$result['assignment']->strategy] ?? $result['assignment']->strategy }})</dd>
                            <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.chosen_carrier') }}</dt>
                            <dd class="col-sm-7" data-chosen-carrier>{{ $result['assignment']->quote?->name }} <small class="text-muted">#{{ $result['assignment']->quote?->id }}</small></dd>
                            <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.rate') }}</dt>
                            <dd class="col-sm-7">{{ $result['assignment']->quote?->rate->format(true) }}</dd>
                            <dt class="col-sm-5">{{ trans('logistics::courier_rule.labels.eta') }}</dt>
                            <dd class="col-sm-7">{{ $result['assignment']->quote?->etaDays ?? '-' }}</dd>
                        @else
                            <dd class="col-sm-12 text-danger" data-not-serviceable>{{ trans('logistics::courier_rule.labels.not_serviceable') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        @endif
    </div>
</div>
@stop
