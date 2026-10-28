{{--
    Courier rule builder shared by create and edit (plan M13 §7): every condition present must
    match; blank conditions match anything. Comma lists are split by CourierRuleRequest.
--}}
@php
    $rule = $rule ?? null;
    $conditions = (array) ($rule?->conditions ?? []);
    $commaValue = fn (string $key) => old($key, implode(', ', (array) ($key === 'preferred_carrier_ids' ? ($rule?->preferred_carrier_ids ?? []) : ($conditions[$key] ?? []))));
    $selectedZones = (array) old('zones', $conditions['zones'] ?? []);
    $selectedPayment = (array) old('payment', $conditions['payment'] ?? []);
    $isEnabled = (int) old('status', $rule?->status ?? config('core.enabled')) === (int) config('core.enabled');
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('logistics::courier_rule.titles.general') }}</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                {{ normalText('name', 'logistics::courier_rule.labels.name', $errors, old('name', $rule?->name), ['class' => 'form-control required', 'maxlength' => 120]) }}
            </div>
            <div class="col-md-3">
                {{ normalText('priority', 'logistics::courier_rule.labels.priority', $errors, old('priority', $rule?->priority ?? 10), ['class' => 'form-control required', 'maxlength' => 4]) }}
                <small class="text-muted">{{ trans('logistics::courier_rule.labels.priority_help') }}</small>
            </div>
            <div class="col-md-3">
                <label>{{ trans('logistics::courier_rule.labels.status') }}</label>
                <div><label class="switch"><input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}><span class="slider round"></span></label></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                {{ normalSelect('courier_id', 'logistics::courier_rule.labels.courier', $errors, $couriers, old('courier_id', $rule?->courier_id)) }}
            </div>
            <div class="col-md-4">
                {{ normalSelect('strategy', 'logistics::courier_rule.labels.strategy', $errors, $strategies, old('strategy', $rule?->strategy ?? 'recommended')) }}
            </div>
            <div class="col-md-4">
                {{ normalText('preferred_carrier_ids', 'logistics::courier_rule.labels.preferred_carrier_ids', $errors, $commaValue('preferred_carrier_ids'), ['class' => 'form-control', 'maxlength' => 400]) }}
                <small class="text-muted">{{ trans('logistics::courier_rule.labels.preferred_help') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="card card-info card-outline mt-3">
    <div class="card-header"><strong>{{ trans('logistics::courier_rule.titles.conditions') }}</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label>{{ trans('logistics::courier_rule.labels.zones') }}</label>
                <div>
                    @foreach ($zones as $value => $label)
                        <label class="mr-3"><input type="checkbox" name="zones[]" value="{{ $value }}" {{ in_array($value, $selectedZones, true) ? 'checked' : '' }}> {{ $label }}</label>
                    @endforeach
                </div>
                @error('zones.*')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-6 form-group">
                <label>{{ trans('logistics::courier_rule.labels.payment') }}</label>
                <div>
                    @foreach ($paymentModes as $value => $label)
                        <label class="mr-3"><input type="checkbox" name="payment[]" value="{{ $value }}" {{ in_array($value, $selectedPayment, true) ? 'checked' : '' }}> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {{ normalText('pincode_prefixes', 'logistics::courier_rule.labels.pincode_prefixes', $errors, $commaValue('pincode_prefixes'), ['class' => 'form-control', 'maxlength' => 2000]) }}
                @error('pincode_prefixes.*')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-6">
                {{ normalText('state_ids', 'logistics::courier_rule.labels.state_ids', $errors, $commaValue('state_ids'), ['class' => 'form-control', 'maxlength' => 500]) }}
                @error('state_ids.*')<label class="error">{{ $message }}</label>@enderror
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                {{ normalText('weight_min_g', 'logistics::courier_rule.labels.weight_min_g', $errors, old('weight_min_g', $conditions['weight_min_g'] ?? null), ['class' => 'form-control', 'maxlength' => 7]) }}
            </div>
            <div class="col-md-3">
                {{ normalText('weight_max_g', 'logistics::courier_rule.labels.weight_max_g', $errors, old('weight_max_g', $conditions['weight_max_g'] ?? null), ['class' => 'form-control', 'maxlength' => 7]) }}
            </div>
            <div class="col-md-3">
                {{ normalText('seller_ids', 'logistics::courier_rule.labels.seller_ids', $errors, $commaValue('seller_ids'), ['class' => 'form-control', 'maxlength' => 2000]) }}
                @error('seller_ids.*')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-3">
                {{ normalText('category_ids', 'logistics::courier_rule.labels.category_ids', $errors, $commaValue('category_ids'), ['class' => 'form-control', 'maxlength' => 2000]) }}
                @error('category_ids.*')<label class="error">{{ $message }}</label>@enderror
            </div>
        </div>
        <small class="text-muted">{{ trans('logistics::courier_rule.labels.comma_help') }}</small>

        @if ($rule !== null)
            <div class="mt-3">
                <label>{{ trans('logistics::courier_rule.titles.preview') }}</label>
                <pre class="mb-0 p-2 bg-light" style="white-space: pre-wrap;">{{ json_encode(['conditions' => $rule->conditions, 'preferred_carrier_ids' => $rule->preferred_carrier_ids, 'strategy' => $rule->strategy], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</div>
